# Show "Clear all" whenever anything narrows, as the clear link did before the chips row.
s/clear\.hidden = !response\.clearall;/clear.hidden = !response.hasfilters;/;
