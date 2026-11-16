# Drop the grouped order from the panel's addresses: closing a panel, or opening another, forgets it.
s/        unset\(\$params\[criteria::PARAM_OPEN\]\);/        unset(\$params[criteria::PARAM_OPEN], \$params[criteria::PARAM_PSORT]);/;
