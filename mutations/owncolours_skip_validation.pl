# Validate the colours of a form whose box is unticked: a manager is refused over two disabled fields.
s/\$owncolours = !empty\(\$data\['owncolours'\]\);/\$owncolours = true;/;
