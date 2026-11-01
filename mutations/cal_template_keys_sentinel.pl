# Drop the empty keys member, so a form with every chip cleared names no key and shows them all.
s/            <input type="hidden" name="keys\[\]" value="">\n//;
