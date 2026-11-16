# Tell the line no result count: its bar keeps the page's number.
s/total: typeof response\.countnumber === 'string' \? response\.countnumber : undefined,/total: undefined,/;
