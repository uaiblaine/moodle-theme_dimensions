# The forward to the route drops the zoom.
s/            if \(self::zoom_from_request\(\) !== ''\) \{\n                \$params\[self::PARAM_ZOOM\] = self::zoom_from_request\(\);\n            \}\n//;
