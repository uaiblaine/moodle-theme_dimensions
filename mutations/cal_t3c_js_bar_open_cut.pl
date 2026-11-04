# Leave an open bar's cut at the edge of the days held undashed, as the open bar once faded.
s/\(bar\.after \? ' dim-cal__trailbar--after' : ''\)/(bar.after \&\& !bar.open ? ' dim-cal__trailbar--after' : '')/;
