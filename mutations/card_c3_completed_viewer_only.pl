# Count anybody's completion of the course as the viewer's.
s/"userid = :userid AND course \{\$insql\} AND timecompleted > 0"/"(userid = :userid OR userid > 0) AND course {\$insql} AND timecompleted > 0"/;
