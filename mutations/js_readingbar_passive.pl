# Make the scroll listener active: the browser waits for it before every scroll.
s/window\.addEventListener\('scroll', onScroll, \{passive: true\}\);/window.addEventListener('scroll', onScroll, {passive: false});/;
