# Take any class that exists for the image picker's helper, so a class without register() or TYPE is called.
s/return class_exists\(\$element\) && method_exists\(\$element, 'register'\) && defined\(\$element \. '::TYPE'\);/return class_exists(\$element);/;
