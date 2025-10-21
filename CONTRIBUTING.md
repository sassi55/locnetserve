# Contributing to LocNetServe

Thank you for your interest in contributing to LocNetServe\! Your help is vital for improving this project. By contributing, you agree to abide by the project's [Code of Conduct](https://www.google.com/search?q=CODE_OF_CONDUCT.md).

## How to Contribute

There are many ways you can contribute, even if you don't write code.

  * **Report Bugs**: If you find a bug, please check the [issues page](https://www.google.com/search?q=https://github.com/sassisouid/locnetserve/issues) to see if it has already been reported. If not, open a new issue with a clear title and detailed description.
  * **Suggest Features**: Have an idea for a new feature or an improvement? Open an issue and describe your suggestion.
  * **Improve Documentation**: We always appreciate contributions to improve the `README.md`, comments, or add new documentation sections.
  * [cite\_start]**Translate**: The project supports multiple languages [cite: 1] like French, English, and Spanish. If you speak another language, you could help translate the command descriptions or messages.
  * **Code Contributions**: If you want to contribute code, please follow the guidelines below.

## Code Contribution Guidelines

### 1\. Fork the Repository

First, fork the [LocNetServe repository](https://www.google.com/search?q=https://github.com/sassisouid/locnetserve) on GitHub to your own account.

### 2\. Clone Your Fork

Clone your forked repository to your local machine.

```bash
git clone https://github.com/sassisouid/locnetserve.git
cd locnetserve
```

### 3\. Create a New Branch

Create a new branch for your feature or bug fix. Use a descriptive name that relates to your work.

```bash
git checkout -b feature/your-awesome-feature
```

### 4\. Set Up Your Development Environment

  * [cite\_start]Ensure you have **Python 3.x** and **AutoHotkey v2.0** installed on your system[cite: 1].
  * Install the required Python libraries. A `requirements.txt` file is not currently available, but you can install the dependencies manually:
      * `pip install colorama`
      * `pip install psutil`
      * `pip install tqdm`
  * [cite\_start]LocNetServe uses a configuration file (`config.json`) and a commands file (`cmd.json`)[cite: 2]. [cite\_start]These files are used by both the Python and AutoHotkey components[cite: 1]. [cite\_start]You may need to adjust the paths within `config.json` to match your local setup[cite: 2].

### 5\. Make Your Changes

  * Write clean, readable code with comments where necessary.
  * [cite\_start]The project uses a modular structure, so try to keep your changes within the relevant files (e.g., `mysql.py` for MySQL functions, `php.py` for PHP functions, etc.)[cite: 3, 4, 5].
  * When adding new commands, remember to update the `cmd.json` file with a description of the new command.

### 6\. Test Your Changes

Run the CLI (`lns.exe`) to test your changes and make sure they don't introduce any new bugs.

### 7\. Commit Your Changes

Commit your changes with a clear, concise, and descriptive message.

```bash
git commit -m "feat: Add a new command for listing databases"
```

### 8\. Push to GitHub

Push your branch to your forked repository on GitHub.

```bash
git push origin feature/your-awesome-feature
```

### 9\. Create a Pull Request (PR)

Go to your repository on GitHub and open a new Pull Request. Provide a clear description of your changes and reference any related issues.

Thank you for helping make this project better\!