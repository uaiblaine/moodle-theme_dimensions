# Ask a batch at every zoom: by days and weeks a request is three months, not one.
s/\(zoom === 'month' \? Math\.max\(1, Math\.min\(BATCH_MONTHS, capOf\(zoom\) - held\)\) : 1\)/Math.max(1, Math.min(BATCH_MONTHS, capOf(zoom) - held))/;
