# Listen for Escape on the dropdown's panel only, not on the document.
s/document\.addEventListener\('keydown', escape\);/popover.panel.current?.addEventListener('keydown', escape);/;
