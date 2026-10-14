# Accept anything as the category id, so a path that is not a number reaches the controller.
s/            new path_parameter\(name: 'category', type: param::INT\),/            new path_parameter(name: 'category', type: param::RAW),/;
