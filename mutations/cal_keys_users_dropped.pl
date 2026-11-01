# Never spare the vault the users argument.
s/\$users = \$off\('users'\) \? \[\] : \$scope->users;/\$users = \$scope->users;/;
