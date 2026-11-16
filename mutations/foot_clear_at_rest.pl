# Offer "Clear filters" with nothing to clear.
s/ data-action="dim-clear"\{\{\^hasfilters\}\} hidden\{\{\/hasfilters\}\}>\{\{clearfilterslabel\}\}/ data-action="dim-clear">{{clearfilterslabel}}/;
