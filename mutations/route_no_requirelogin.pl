# Force a login on the route, so a visitor of a public course never sees it under forcelogin.
s/        pathtypes: \[\n            new path_parameter\(name: 'course', type: param::INT\),\n        \],\n    \)\]/        pathtypes: [\n            new path_parameter(name: 'course', type: param::INT),\n        ],\n        requirelogin: new \\core\\router\\require_login(),\n    )]/;
