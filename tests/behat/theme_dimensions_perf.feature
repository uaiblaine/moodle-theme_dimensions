@theme @theme_dimensions @theme_dimensions_perf @javascript
Feature: The category showcase's first paint, measured in a throttled browser
  In order to keep the category showcase fast on a mid-range phone on a slow network
  As a developer of the theme
  I need the browser's own paint, blocking, shift, interaction and request numbers of its pages, run by run

  # The showcase's performance probe (theme_dimensions_perf_probe.php). It skips unless the site opts
  # in ($CFG->behat_theme_dimensions_perfprobe = true, or THEME_DIMENSIONS_PERFPROBE=1 in the runner's
  # environment), so ordinary suites and CI never spend its minutes. Opted in, every probe step loads
  # its page five times under 4x CPU throttling and a 150 ms / 1.6 Mbps network (Chrome DevTools,
  # through chromedriver's vendor endpoint), reads the browser's own entries once the page is quiet,
  # presses one control for INP, and writes every run and the medians as JSON under the faildump
  # path, in theme_dimensions_perf/. The timings are a trend; nothing here fails on a number. The
  # deterministic counts (scripts and stylesheets before load, their bytes, DOM nodes, HTML bytes)
  # are read from the cold series.

  Background:
    Given the theme_dimensions performance probe is enabled
    And the following config values are set as admin:
      | theme | dimensions |
    And the showcase performance fixture exists

  @theme_dimensions_perf_flat
  Scenario: A student's flat category page of courses with covers
    Given I log in as "perfstudent"
    When I probe the "cold" first paint of "category:PERFFLAT|sort=name" as "student-flat" pressing ".dim-line__scope label.dim-pill:not([hidden]):nth-of-type(2)"
    And I probe the "warm" first paint of "category:PERFFLAT|sort=name" as "student-flat" pressing ".dim-line__scope label.dim-pill:not([hidden]):nth-of-type(2)"
    Then "body.dim-js" "css_element" should exist

  @theme_dimensions_perf_root
  Scenario: A student's course index, the directory of areas
    Given I log in as "perfstudent"
    When I probe the "cold" first paint of "index:" as "student-root" pressing "[data-region='dim-poster-control']"
    And I probe the "warm" first paint of "index:" as "student-root" pressing "[data-region='dim-poster-control']"
    Then "body.dim-panel-js" "css_element" should exist

  @theme_dimensions_perf_grouped
  Scenario: A student's grouped category page, its areas as posters
    Given I log in as "perfstudent"
    When I probe the "cold" first paint of "category:PERFAREAS" as "student-grouped" pressing "[data-region='dim-poster-control']"
    And I probe the "warm" first paint of "category:PERFAREAS" as "student-grouped" pressing "[data-region='dim-poster-control']"
    Then "body.dim-panel-js" "css_element" should exist

  @theme_dimensions_perf_visitor
  Scenario: A visitor's public category page, with the site forcing login and the live listing open
    Given the following config values are set as admin:
      | forcelogin           | 1 |                  |
      | categorypublicclient | 1 | theme_dimensions |
    When I probe the "cold" first paint of "public:PERFFLAT" as "visitor-flat" pressing "[data-region='dim-view-menu'] button[aria-checked='false']"
    And I probe the "warm" first paint of "public:PERFFLAT" as "visitor-flat" pressing "[data-region='dim-view-menu'] button[aria-checked='false']"
    Then "body.dim-public-js" "css_element" should exist
