# Writing Skills

Skills are YAML files that define a set of actions OpenDispatch can perform.

## Skill Structure

A skill YAML file contains:

- **skill_id** — unique identifier (e.g. `tesla`)
- **name** — display name
- **version** — semver (e.g. `1.0.0`)
- **description** — what the skill does
- **author** — who wrote it
- **tags** — categories (must be from the allowed list in `tags.yaml`)
- **actions** — the things users can do

## Actions

Each action has:

- **id** — dot-separated identifier (e.g. `vehicle.unlock`)
- **title** — human-readable name
- **description** — what it does
- **examples** — natural language phrases that trigger this action
- **parameters** — optional input values

## Example

```yaml
skill_id: my_skill
name: My Skill
version: 1.0.0
description: "A simple example skill"
author: yourname
tags:
  - productivity
actions:
  - id: task.create
    title: Create Task
    description: "Create a new task"
    examples:
      - "create a task"
      - "add a new todo"
```

## Submitting Skills

1. Fork the skills repository
2. Add your skill directory under `skills/`
3. Include a `skill.yaml` and optionally an `icon.png`
4. Submit a pull request
