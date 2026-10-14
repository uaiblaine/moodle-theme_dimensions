# Draw the tracks without their instances' names.
s/\$methods\[\$i\]\['trackname'\] = \$this->instance_title\(\$instance, 'enrol_' \. \$instance->enrol\);/\$methods[\$i]['trackname'] = null;/;
