# Stop keeping the three other holders of the panel in step with the address. The toolbar's
# no-JavaScript submit, the hero's fold form and the clear link then reload the
# page with the panel it had when it LOADED: folding the hero closes the panel the reader has open,
# or reopens one they closed. The call is removed from writeAddress() only; syncHolders() is still
# used by the back button, so the module still builds.
s/\n    syncHolders\(\);\n\};/\n};/;
