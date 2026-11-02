# Keep Boost's cap of the column on the calendar page.
s/        #page\.drawers \.main-inner \{\n            max-width: none;\n        \}/        #page.drawers .main-inner {\n            margin-top: 0;\n        }/;
