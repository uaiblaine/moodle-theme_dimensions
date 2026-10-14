# Hand the delegate a logged-in request: with forced login off, user 0 may list every listed course.
s/'slice' => \(int\) \$params\['slice'\],\n            'anonymous' => true,/'slice' => (int) \$params['slice'],\n            'anonymous' => false,/;
