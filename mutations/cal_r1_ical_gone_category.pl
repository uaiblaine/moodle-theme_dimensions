# Keep the events of a category that no longer exists.
s/empty\(\$event->categoryid\) \|\| isset\(\$listed\[\(int\) \$event->categoryid\]\)/empty(\$event->categoryid) || isset(\$listed[(int) \$event->categoryid]) || !isset(\$categories[(int) \$event->categoryid])/;
