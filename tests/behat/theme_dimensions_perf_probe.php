<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

// NOTE: no MOODLE_INTERNAL check here on purpose - the Behat context that requires this file is
// included before config.php exists, and the guard's die() would silently end the whole run.

use Facebook\WebDriver\Chrome\ChromeDevToolsDriver;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;

/**
 * The showcase's browser probe: a throttled Chrome, the browser's own performance entries, five runs, the median.
 *
 * Not a context (its file name does not start with behat_), so Behat never loads it as one:
 * behat_theme_dimensions_perf requires it, and it depends on nothing but php-webdriver, so a
 * script driving a Selenium session of its own can use it too.
 *
 * Throttling goes through the Chrome DevTools Protocol, sent over chromedriver's vendor endpoint
 * (`POST {wd_host}/session/{id}/goog/cdp/execute`, php-webdriver's ChromeDevToolsDriver), which the
 * Selenium grid forwards to the node. The measurement is the browser's: a collector registered on
 * every new document before any of the page's own script (Page.addScriptToEvaluateOnNewDocument)
 * observes paint, largest-contentful-paint, layout-shift, longtask and event timing, and the
 * deterministic counts are read from resource and navigation timing after the page has gone quiet.
 *
 * Off unless a site opts in ({@see options()}): the probe takes minutes, and its timings are a
 * trend, not a gate.
 *
 * @package    theme_dimensions
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class theme_dimensions_perf_probe {
    /**
     * @var string The config property that opts a site in: true, or an array overriding {@see DEFAULTS}.
     *
     * It carries the behat_ prefix because the Behat process keeps nothing else of config.php:
     * core's behat_clean_init_config() unsets every $CFG property outside a short allowlist that is
     * not behat_-prefixed, before any step runs.
     */
    public const CONFIG = 'behat_theme_dimensions_perfprobe';

    /** @var string The environment variable that opts a run in: 1, or a JSON object overriding {@see DEFAULTS}. */
    public const ENV = 'THEME_DIMENSIONS_PERFPROBE';

    /**
     * @var array The run's conditions: Lighthouse's mobile profile (4x CPU, 150 ms, 1.6 Mbps down, 750 kbps up).
     *
     * runs: measured loads per series; cpu: Emulation.setCPUThrottlingRate; latency: ms added to
     * every request; download and upload: bits per second; quiet: ms with no new performance entry
     * and no pending JavaScript that counts as settled; timeout: seconds a load may take to settle.
     */
    public const DEFAULTS = [
        'runs' => 5,
        'cpu' => 4,
        'latency' => 150,
        'download' => 1638400,
        'upload' => 768000,
        'quiet' => 2000,
        'timeout' => 120,
    ];

    /** @var string The collector, registered on every new document of the session. */
    private const COLLECTOR = <<<'JS'
(function() {
    if (window.__dimPerf) {
        return;
    }
    var p = window.__dimPerf = {last: 0, lcp: null, lcpel: '', lcpsize: 0, shifts: [], longtasks: [], events: []};
    var touch = function() {
        p.last = performance.now();
    };
    var watch = function(type, fn, extra) {
        try {
            var options = {type: type, buffered: true};
            Object.keys(extra || {}).forEach(function(key) {
                options[key] = extra[key];
            });
            new PerformanceObserver(function(list) {
                list.getEntries().forEach(fn);
                touch();
            }).observe(options);
        } catch (e) {
            // An entry type this browser does not know is simply not measured.
        }
    };
    var describe = function(el) {
        if (!el || !el.tagName) {
            return '';
        }
        var name = el.tagName.toLowerCase() + (el.id ? '#' + el.id : '');
        return name + Array.prototype.slice.call(el.classList || [], 0, 3).map(function(c) {
            return '.' + c;
        }).join('');
    };
    watch('largest-contentful-paint', function(e) {
        p.lcp = e.renderTime || e.loadTime || e.startTime;
        p.lcpel = describe(e.element) || e.url || '';
        p.lcpsize = e.size;
    });
    watch('layout-shift', function(e) {
        if (!e.hadRecentInput) {
            p.shifts.push([e.startTime, e.value]);
        }
    });
    watch('longtask', function(e) {
        p.longtasks.push([e.startTime, e.duration]);
    });
    watch('event', function(e) {
        if (e.interactionId) {
            p.events.push([e.name, e.startTime, e.duration, e.interactionId]);
        }
    }, {durationThreshold: 16});
    watch('resource', function() {
        return;
    });
    watch('paint', function() {
        return;
    });
    window.addEventListener('load', touch);
    p.idle = function(quiet) {
        if (document.readyState !== 'complete') {
            return false;
        }
        if (window.M && M.util && M.util.pending_js && M.util.pending_js.length) {
            touch();
            return false;
        }
        return performance.now() - p.last >= quiet;
    };
    p.cls = function() {
        var max = 0, current = 0, first = 0, previous = 0;
        p.shifts.forEach(function(shift) {
            if (current && shift[0] - previous < 1000 && shift[0] - first < 5000) {
                current += shift[1];
            } else {
                current = shift[1];
                first = shift[0];
            }
            previous = shift[0];
            max = Math.max(max, current);
        });
        return max;
    };
    p.kind = function(entry) {
        var url = entry.name;
        if (entry.initiatorType === 'script' || /\.m?js(\?|$)|\/requirejs\.php\/|\/javascript\.php\/|\/js\.php\//.test(url)) {
            return 'scripts';
        }
        if (/\.css(\?|$)|\/styles\.php\/|\/styles_debug\.php/.test(url) && entry.initiatorType !== 'css') {
            return 'stylesheets';
        }
        return 'other';
    };
    p.tally = function(entries) {
        var out = {requests: entries.length};
        ['scripts', 'stylesheets', 'other'].forEach(function(kind) {
            out[kind] = {count: 0, transfer: 0, decoded: 0};
        });
        entries.forEach(function(entry) {
            var bucket = out[p.kind(entry)];
            bucket.count++;
            bucket.transfer += entry.transferSize || 0;
            bucket.decoded += entry.decodedBodySize || 0;
        });
        return out;
    };
    p.snapshot = function() {
        var nav = performance.getEntriesByType('navigation')[0] || {};
        var paint = performance.getEntriesByName('first-contentful-paint')[0];
        var fcp = paint ? paint.startTime : null;
        var loadend = nav.loadEventEnd || 0;
        var tbt = 0;
        p.longtasks.forEach(function(task) {
            var end = task[0] + task[1];
            if (fcp === null || end <= fcp) {
                return;
            }
            var blocking = end - Math.max(task[0], fcp) - 50;
            tbt += blocking > 0 ? blocking : 0;
        });
        var resources = performance.getEntriesByType('resource');
        return {
            ttfb: nav.responseStart || null,
            fcp: fcp,
            lcp: p.lcp,
            lcpelement: p.lcpel,
            tbt: tbt,
            cls: p.cls(),
            longtasks: p.longtasks.length,
            domcontentloaded: nav.domContentLoadedEventEnd || null,
            load: loadend || null,
            settled: performance.now(),
            htmltransfer: nav.transferSize || 0,
            htmlencoded: nav.encodedBodySize || 0,
            htmldecoded: nav.decodedBodySize || 0,
            domnodes: document.getElementsByTagName('*').length,
            beforeload: p.tally(resources.filter(function(entry) {
                return entry.startTime < loadend;
            })),
            untilsettled: p.tally(resources),
            viewport: [window.innerWidth, window.innerHeight]
        };
    };
    p.inp = function(since) {
        var byid = {};
        p.events.forEach(function(event) {
            if (event[1] >= since) {
                byid[event[3]] = Math.max(byid[event[3]] || 0, event[2]);
            }
        });
        var ids = Object.keys(byid);
        return {interactions: ids.length, inp: ids.length ? Math.max.apply(null, ids.map(function(id) {
            return byid[id];
        })) : null};
    };
})();
JS;

    /** @var RemoteWebDriver The session's driver. */
    private RemoteWebDriver $driver;

    /** @var array The run's conditions, {@see DEFAULTS} merged with the opt-in. */
    private array $options;

    /** @var bool Whether the collector is registered on this session. */
    private bool $armed = false;

    /**
     * Constructor.
     *
     * @param RemoteWebDriver $driver A Chrome session.
     * @param array $options The conditions, as {@see options()} returns them.
     */
    public function __construct(RemoteWebDriver $driver, array $options) {
        $this->driver = $driver;
        $this->options = $options;
    }

    /**
     * Whether, and how, this site runs the probe: null unless it opted in.
     *
     * The opt-in is the config property (`$CFG->behat_theme_dimensions_perfprobe = true;` or an array),
     * else the environment variable (`1`, or a JSON object). Anything empty, `0` or false is off, so
     * an ordinary suite and CI skip the probe's scenarios.
     *
     * @return array|null The conditions, or null when the site did not opt in.
     */
    public static function options(): ?array {
        global $CFG;
        $raw = $CFG->{self::CONFIG} ?? null;
        if (empty($raw)) {
            $raw = getenv(self::ENV);
            if ($raw === false || trim((string) $raw) === '' || trim((string) $raw) === '0') {
                return null;
            }
            $decoded = json_decode((string) $raw, true);
            $raw = is_array($decoded) ? $decoded : true;
        }
        if (!is_array($raw)) {
            return self::DEFAULTS;
        }
        $options = self::DEFAULTS;
        foreach (array_intersect_key($raw, self::DEFAULTS) as $name => $value) {
            $options[$name] = max(0, (int) $value);
        }
        $options['runs'] = max(1, $options['runs']);
        $options['cpu'] = max(1, $options['cpu']);
        return $options;
    }

    /**
     * Send one DevTools command through chromedriver's vendor endpoint.
     *
     * @param string $command The CDP method.
     * @param array $params Its parameters.
     * @return array The result.
     */
    public function cdp(string $command, array $params = []): array {
        $result = (new ChromeDevToolsDriver($this->driver))->execute($command, $params);
        return is_array($result) ? $result : [];
    }

    /**
     * Register the collector on every new document of the session, once.
     *
     * @return void
     */
    private function arm(): void {
        if (!$this->armed) {
            $this->cdp('Page.addScriptToEvaluateOnNewDocument', ['source' => self::COLLECTOR]);
            $this->armed = true;
        }
    }

    /**
     * Put the session under the run's conditions: a slow CPU, a slow network, and for a cold run no cache.
     *
     * @param bool $cold Whether the run starts with an empty browser cache.
     * @return void
     */
    private function throttle(bool $cold): void {
        $this->cdp('Network.enable');
        if ($cold) {
            $this->cdp('Network.clearBrowserCache');
        }
        $this->cdp('Network.setCacheDisabled', ['cacheDisabled' => $cold]);
        $this->cdp('Emulation.setCPUThrottlingRate', ['rate' => $this->options['cpu']]);
        $this->cdp('Network.emulateNetworkConditions', [
            'offline' => false,
            'latency' => $this->options['latency'],
            'downloadThroughput' => intdiv($this->options['download'], 8),
            'uploadThroughput' => intdiv($this->options['upload'], 8),
        ]);
    }

    /**
     * Give the session back as it was: full speed, no network emulation, the cache on.
     *
     * Called after every run, so a step that fails half way leaves no throttled browser to the next one.
     *
     * @return void
     */
    public function release(): void {
        $this->cdp('Emulation.setCPUThrottlingRate', ['rate' => 1]);
        $this->cdp('Network.emulateNetworkConditions', [
            'offline' => false, 'latency' => 0, 'downloadThroughput' => -1, 'uploadThroughput' => -1,
        ]);
        $this->cdp('Network.setCacheDisabled', ['cacheDisabled' => false]);
    }

    /**
     * Wait until the page has settled: loaded, no pending JavaScript, and no new entry for the quiet window.
     *
     * @return bool False when the timeout ran out first.
     */
    private function settle(): bool {
        $deadline = microtime(true) + $this->options['timeout'];
        $script = 'return !!(window.__dimPerf && window.__dimPerf.idle(' . (int) $this->options['quiet'] . '));';
        while (microtime(true) < $deadline) {
            if ($this->driver->executeScript($script)) {
                return true;
            }
            usleep(250000);
        }
        return false;
    }

    /**
     * One measured load of a page, and optionally one press on it.
     *
     * The press is a real WebDriver click, so the browser records it as a trusted interaction; its
     * INP is the longest event of that interaction once the page has settled again. A press that
     * finds nothing to press is reported, never failed: the timings are a trend.
     *
     * @param string $url The page.
     * @param bool $cold Whether the run starts with an empty browser cache.
     * @param string $press A CSS selector of the control to press after the load, '' for none.
     * @return array The run's numbers.
     */
    public function load(string $url, bool $cold, string $press = ''): array {
        $this->arm();
        $this->driver->get('about:blank');
        $this->throttle($cold);
        try {
            $started = microtime(true);
            $this->driver->get($url);
            $settled = $this->settle();
            $run = (array) $this->driver->executeScript('return window.__dimPerf ? window.__dimPerf.snapshot() : null;');
            $run['wallms'] = round(1000 * (microtime(true) - $started));
            $run['timedout'] = !$settled;
            if ($press !== '') {
                $run += $this->press($press);
            }
        } finally {
            $this->release();
        }
        return $run;
    }

    /**
     * Press a control on the settled page and read the interaction's latency.
     *
     * @param string $selector The control's CSS selector.
     * @return array inp (ms, or null under the 16 ms threshold), interactions, and what was pressed.
     */
    private function press(string $selector): array {
        $elements = $this->driver->findElements(WebDriverBy::cssSelector($selector));
        $target = null;
        foreach ($elements as $element) {
            if ($element->isDisplayed()) {
                $target = $element;
                break;
            }
        }
        if ($target === null) {
            return ['inp' => null, 'interactions' => 0, 'pressed' => 'nothing visible matched ' . $selector];
        }
        $since = (float) $this->driver->executeScript('return performance.now();');
        $target->click();
        $this->settle();
        $answer = (array) $this->driver->executeScript('return window.__dimPerf.inp(' . $since . ');');
        return $answer + ['pressed' => $selector];
    }

    /**
     * A series: the configured number of measured loads (a warm series first loads the page once, unmeasured).
     *
     * @param string $url The page.
     * @param bool $cold Whether every run starts with an empty browser cache.
     * @param string $press The control to press after each load, '' for none.
     * @return array The runs.
     */
    public function series(string $url, bool $cold, string $press = ''): array {
        if (!$cold) {
            $this->arm();
            $this->driver->get($url);
            $this->settle();
        }
        $runs = [];
        for ($i = 0; $i < $this->options['runs']; $i++) {
            $runs[] = $this->load($url, $cold, $press);
        }
        return $runs;
    }

    /**
     * The median of every number across the runs, nested counts included; a value missing from a run is left out.
     *
     * @param array $runs The runs, as {@see load()} returns them.
     * @return array The medians, keyed like a run.
     */
    public static function median(array $runs): array {
        $keys = [];
        foreach ($runs as $run) {
            foreach ((array) $run as $key => $value) {
                $keys[$key] = true;
            }
        }
        $median = [];
        foreach (array_keys($keys) as $key) {
            $values = [];
            $nested = [];
            foreach ($runs as $run) {
                $value = $run[$key] ?? null;
                if (is_array($value) && !array_is_list($value)) {
                    $nested[] = $value;
                } else if (is_int($value) || is_float($value)) {
                    $values[] = $value;
                } else if ($value !== null && !isset($median[$key])) {
                    $median[$key] = $value;
                }
            }
            if ($nested) {
                $median[$key] = self::median($nested);
            } else if ($values) {
                sort($values);
                $middle = intdiv(count($values), 2);
                $median[$key] = count($values) % 2 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
            }
        }
        return $median;
    }

    /**
     * The report of a series: what was measured, under which conditions, every run and the medians.
     *
     * @param string $label The scenario's name for the page.
     * @param string $url The page.
     * @param bool $cold Whether the runs were cold.
     * @param array $runs The runs.
     * @return array The report.
     */
    public function report(string $label, string $url, bool $cold, array $runs): array {
        $capabilities = $this->driver->getCapabilities();
        $browser = '';
        if ($capabilities) {
            $version = $capabilities->getCapability('browserVersion') ?: $capabilities->getVersion();
            $browser = trim($capabilities->getBrowserName() . ' ' . $version);
        }
        return [
            'probe' => 'theme_dimensions_perf',
            'label' => $label,
            'mode' => $cold ? 'cold' : 'warm',
            'url' => $url,
            'measured' => date('c'),
            'conditions' => $this->options,
            'browser' => $browser,
            'session' => $this->driver->getSessionID(),
            'median' => self::median($runs),
            'runs' => $runs,
        ];
    }

    /**
     * Write a report as JSON into a directory, created if need be.
     *
     * @param string $dir The directory.
     * @param array $report The report.
     * @return string The file written.
     */
    public static function write(string $dir, array $report): string {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = date('Ymd-His') . '-' . preg_replace('/[^a-z0-9_-]+/i', '_', $report['label'] . '-' . $report['mode']);
        $path = rtrim($dir, '/') . '/' . $name . '.json';
        file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $path;
    }
}
