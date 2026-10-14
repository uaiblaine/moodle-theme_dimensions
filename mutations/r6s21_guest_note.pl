# Drop the note that guest access enrols nobody.
s/\$entry\['guestnote'\] = \$isvisitor && !\$this->resolution->loginwayin;/\$entry['guestnote'] = false;/
