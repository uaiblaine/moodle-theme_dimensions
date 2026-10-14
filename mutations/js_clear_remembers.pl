# Clear the filters in place without clearing the remembered ones: the next bare visit brings them back.
s/    \/\/ Clearing the filters clears the remembered ones of this category too\.\n    remember\(false\);\n//;
