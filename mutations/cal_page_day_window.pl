# Keep any real day, inside the month grid's window or not.
s/        return \$day >= \$from && \$day <= \$to \? \$day : '';/        return \$day;/;
