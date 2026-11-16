# Reset the grouped order with a dropped panel, as before it held with none open.
s/        \$criteria->open = 0;\n    \}/        \$criteria->open = 0;\n        \$criteria->psort = '';\n    }/;
