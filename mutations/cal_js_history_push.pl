# Push every write, a change of state included.
s/        writeAddress\(address\(props\.urls\.page, month, next, moved \? '' : opened\.current \|\| ''\), write === 'push'\);/        writeAddress(address(props.urls.page, month, next, moved ? '' : opened.current || ''), true);/;
