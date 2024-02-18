# https://github.com/devcontainers/images/blob/main/src/php/.devcontainer/Dockerfile
FROM mcr.microsoft.com/devcontainers/php:0-8.1-bullseye

RUN ln -s /workspace/dobot.sh /usr/bin/dobot;\
    ln -s /workspace/.devcontainer/devcontainer.php.ini\
              /usr/local/etc/php/conf.d/devcontainer.php.ini;

ENV PATH="/workspace/bin/tasks:/workspace/bin/utils:${PATH}"

# RUN pip install mdformat mdformat-gfm