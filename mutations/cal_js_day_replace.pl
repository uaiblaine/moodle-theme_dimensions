# Push an entry for every day opened, so Back walks the days.
s/        setDayfocus\(\(tick\) => tick \+ 1\);\n        writeAddress\(withDay\(window\.location\.href, iso\), false\);/        setDayfocus((tick) => tick + 1);\n        writeAddress(withDay(window.location.href, iso), true);/;
