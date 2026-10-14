# Leave the strip out of the listing's export: both entries build it and neither page draws it.
s/            'pages' => \$this->pages,\n/            'pages' => null,\n/;
