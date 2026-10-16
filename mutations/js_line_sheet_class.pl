# Never lift the toolbar while the sheet is open: the page's own fixed controls draw over the sheet.
s/form\.classList\.toggle\(CLASSES\.sheet, state\.step === TWO_LINES && parts\.filters\.open\);/form.classList.toggle(CLASSES.sheet, false);/;
