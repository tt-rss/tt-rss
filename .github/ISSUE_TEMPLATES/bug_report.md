name: Bug Report
description: Create a report to help us improve
title: "[BUG] "
labels: ["bug"]
body:
  - type: markdown
    attributes:
      value: |
        Thanks for taking the time to fill out this bug report!

  - type: textarea
    id: description
    attributes:
      label: Describe the bug
      description: A clear and concise description of what the bug is.
      placeholder: Tell us what you see!
    validations:
      required: true

  - type: textarea
    id: reproduction
    attributes:
      label: To Reproduce
      description: Steps to reproduce the behavior
      placeholder: |
        1. Go to '...'
        2. Click on '...'
        3. Scroll down to '...'
        4. See error
    validations:
      required: true

  - type: textarea
    id: expected
    attributes:
      label: Expected behavior
      description: A clear and concise description of what you expected to happen.
    validations:
      required: true

  - type: textarea
    id: screenshots
    attributes:
      label: Screenshots
      description: If applicable, add screenshots to help explain your problem.

  - type: input
    id: version
    attributes:
      label: Version
      description: What version of tt-rss are you using?
      placeholder: v1.0.0
    validations:
      required: true

  - type: dropdown
    id: browsers
    attributes:
      label: What browsers are you seeing the problem on?
      multiple: true
      options:
        - Chrome
        - Firefox
        - Safari
        - Microsoft Edge
        - Other

  - type: input
    id: server
    attributes:
      label: Server Information
      description: |
        - OS: [e.g. Ubuntu 22.04]
        - Java Version: [e.g. 21]
        - Database: [e.g. PostgreSQL 15]

  - type: textarea
    id: logs
    attributes:
      label: Relevant log output
      description: Please copy and paste any relevant log output. This will be automatically formatted into code.
      render: shell

  - type: textarea
    id: context
    attributes:
      label: Additional context
      description: Add any other context about the problem here.
