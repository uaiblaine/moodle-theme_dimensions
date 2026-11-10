# Read a master switch that was never written as on, as the theme did before decision D5: a fresh install
# then disagrees with its own settings page, which shows the switch off.
s/return \(\(int\) get_config\(self::COMPONENT, 'focusmode'\)\) === yesno::YES;/return get_config(self::COMPONENT, 'focusmode') === false || ((int) get_config(self::COMPONENT, 'focusmode')) === yesno::YES;/;
