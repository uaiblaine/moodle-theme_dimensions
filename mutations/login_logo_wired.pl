# Stop handing the compact logo to the form column. The panel keeps it and the band above the
# form on a phone falls back to the plugin's logo or the wordmark, so one screen shows the
# organisation's mark and the other does not.
s/\$context->logourl = login::logo_url\(\$this->compact_logo_url\(\)\);/\$context->logourl = login::logo_url();/;
