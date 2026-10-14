# A self enrolment instance with a key is not unrestricted.
s/ \|\| !empty\(\$instance->password\) \|\| \$instance->customint6/ || \$instance->customint6/;
