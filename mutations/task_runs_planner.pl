# Run nothing: the task is scheduled every minute and never builds a sheet.
s/\(\$this->planner \?\? new planner\(\)\)->run\(\);//;
