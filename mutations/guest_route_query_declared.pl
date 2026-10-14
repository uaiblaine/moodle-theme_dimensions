# The route declares the guest parameter, or core strips it before the controller.
s/        queryparams: \[\n            new query_parameter\(name: links::GUEST_PARAM, type: param::RAW, default: ''\),\n        \],\n//;
