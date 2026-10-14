# Read enrol_fee's default cost for a paypal instance that carries none of its own.
s/'paypal' => \(float\) get_config\('enrol_paypal', 'cost'\)/'paypal' => (float) get_config('enrol_fee', 'cost')/;
