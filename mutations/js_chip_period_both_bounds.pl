# Drop only the start when the period's chip is pressed: the end alone stays in force.
s/(\} else if \(param === CHIP_PERIOD\) \{\n        state\.datefrom = '';)\n        state\.dateto = '';/$1/;
