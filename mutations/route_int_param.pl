# Accept anything as the course id, so a path that is not a number reaches the controller.
s/            new path_parameter\(name: 'course', type: param::INT\),/            new path_parameter(name: 'course', type: param::RAW),/;
