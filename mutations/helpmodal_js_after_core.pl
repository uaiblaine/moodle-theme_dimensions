# Listen for the click on the document: the swap may run before core's handler has shown the popover.
s/window\.addEventListener\('click', activate\);/document.addEventListener('click', activate);/;
