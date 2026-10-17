# Leave the marks on the cards when the pointer leaves the form.
s/(const onOut = \(event\) => \{.*?)if \(!next \|\| !form\.contains\(next\)\) \{/$1if (false) {/s;
