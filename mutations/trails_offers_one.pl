# The landing page's question reads the trails other than the one asked about.
s/ AND c\.id = :onlyid'/ AND c.id <> :onlyid'/;
