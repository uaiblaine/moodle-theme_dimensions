# Listen for an event nobody sends, so a star toggled on a card never reaches the list.
s/document\.addEventListener\(FAVCHANGED,/document.addEventListener('dim-comp-nothing',/;
