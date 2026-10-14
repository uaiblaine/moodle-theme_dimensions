# Store the submitted colours as the category's own whether the box is ticked or not.
s/\? !empty\(\$data->owncolours\)/? true/;
