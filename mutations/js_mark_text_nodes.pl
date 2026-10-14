# Walk every node of a card's title instead of its text nodes alone. The marking then meets the
# title's link, or a visitor's span, before its text and reads an element as though it were text,
# which has no value to search (decision E3: the term is marked on the title's TEXT, in the
# heading, linked or not).
s/NodeFilter\.SHOW_TEXT/NodeFilter.SHOW_ALL/;
