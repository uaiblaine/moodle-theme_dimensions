# Stop handing the focus back when the search closes: Escape then leaves it in the field it came from.
s/(\[from, field\.current, opener\.current\])\.find/$1.slice(0, 0).find/;
