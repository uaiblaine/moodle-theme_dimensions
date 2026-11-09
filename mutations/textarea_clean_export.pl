# A cleaned read of a textarea would take core's export, which honours the trust mark.
s/        if \(\$filters && !\$clean\) \{\n            return \$data->export_value\(\);/        if (\$filters) {\n            return \$data->export_value();/;
