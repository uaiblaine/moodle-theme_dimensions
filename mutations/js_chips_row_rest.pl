# Never take the row under the line back to rest: a cleared page keeps its strip.
s/row\.classList\.toggle\('dim-line__chips--rest', !narrowed\);/row.classList.toggle('dim-line__chips--rest', false);/;
