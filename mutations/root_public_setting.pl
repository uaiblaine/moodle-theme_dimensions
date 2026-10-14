# Read the root's own setting as always on: the top level of every site running this theme is
# then on the internet the moment the plugin upgrades, with nothing on any screen saying so and
# no administrator having asked for it. This is the clause that keeps the root private
# by default, which is what an existing site keeps.
s/        return \$stored !== false && \(string\) \$stored === '1';/        return true;/;
