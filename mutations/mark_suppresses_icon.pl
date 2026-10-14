# Keep a local icon beside a known mark: the glyph no longer suppresses the image.
s/                'iconurl' => \(\$mark === ''\) \? self::local_icon_url\(\(string\) \(\$idp\['iconurl'\] \?\? ''\)\) : '',\n/                'iconurl' => self::local_icon_url((string) (\$idp['iconurl'] ?? '')),\n/;
