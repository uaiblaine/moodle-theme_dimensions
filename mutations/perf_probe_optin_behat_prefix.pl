# Name the probe's config opt-in without the behat_ prefix: core's behat_clean_init_config() then
# unsets it in the Behat process and every probe scenario skips on a site that opted in.
s/public const CONFIG = 'behat_theme_dimensions_perfprobe';/public const CONFIG = 'theme_dimensions_perfprobe';/;
