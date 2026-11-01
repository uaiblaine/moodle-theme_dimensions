# Push every write, a change of state included.
s/        writeAddress\(address\(props\.urls\.page, month, next\), write === 'push'\);/        writeAddress(address(props.urls.page, month, next), true);/;
