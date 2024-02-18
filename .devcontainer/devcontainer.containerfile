# https://github.com/devcontainers/images/blob/main/src/php/.devcontainer/Dockerfile
FROM mcr.microsoft.com/devcontainers/php:0-8.1-bullseye

RUN ln -s /workspace/php/dobot.php.ini\
    /usr/local/etc/php/conf.d/dobot.php.ini;

ENV PATH="/workspace/bin:/workspace/tasks:${PATH}"

# RUN pip install mdformat mdformat-gfm