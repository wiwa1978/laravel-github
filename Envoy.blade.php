@servers(['web' => 'root@188.166.52.126'])

@setup
    $repository = 'https://github.com/wiwa1978/laravel-github.git';
    $releases_dir = "/var/www/html/app/releases";
    $app_dir = "/var/www/html/app";
    $release = date("YmdHis");
    $new_release_dir = $releases_dir ."/" . $release;
@endsetup

@story('deploy')
    clone_repository
    copy_storage_and_env
    run_composer
    update_symlinks
    generate_application_key
    deployment_cache
    run_migration
    change_permissions
    clean_old_releases
@endstory

@task('clone_repository', ['on' => ['web']])
    echo 'Cloning repository'
    [ -d {{ $releases_dir }} ] || mkdir -p {{ $releases_dir }}
    git clone --depth 1 {{ $repository }} {{ $new_release_dir }}
    cd {{ $new_release_dir }}
@endtask

@task('copy_storage_and_env', ['on' => ['web']])
    echo 'Copy storage folder'
    rm -Rfv {{ $app_dir }}/storage
    mv {{ $new_release_dir }}/storage {{ $app_dir }}/storage
	ln -s {{ $app_dir }}/storage {{ $new_release_dir }}/storage

    echo 'Copy .env file'
    cd {{ $new_release_dir }}
    cp {{ $new_release_dir }}/.env {{ $app_dir }}/.env
@endtask

@task('run_composer', ['on' => ['web']])
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_release_dir }}
    composer install --ignore-platform-reqs
@endtask

@task('deployment_cache', ['on' => ['web']])
    echo "Clear deployment cache"
    cd {{ $new_release_dir }}
    php artisan view:clear --quiet;
    php artisan cache:clear --quiet;
    php artisan config:cache --quiet;
@endtask

@task('update_symlinks', ['on' => ['web']])
    echo "Linking storage directory"
    rm -rf {{ $new_release_dir }}/storage
	ln -s {{ $app_dir }}/storage {{ $new_release_dir }}/storage
    
    echo 'Linking .env file'
    ln -nfs {{ $app_dir }}/.env {{ $new_release_dir }}/.env

    echo 'Linking current release'
    ln -nfs {{ $new_release_dir }} {{ $app_dir }}/current
@endtask

@task('generate_application_key', ['on' => ['web']])
    echo "Generate App Key"
    cd {{ $app_dir }}/current
    php artisan key:generate;
@endtask

@task('run_migration', ['on' => ['web']])
    echo "Running Migration"
    cd {{ $app_dir }}/current
    php artisan migrate --force --no-interaction;
@endtask

@task('change_permissions', ['on' => ['web']])
    echo 'Change permissions for bootstrap folder'
    cd {{ $new_release_dir }}
    chmod 777 bootstrap/*

    echo 'Change permissions for storage folder'
    chmod 777 -R {{ $new_release_dir }}/storage

    echo 'Change folder owner'
    chgrp -R www-data /var/www/html/app
@endtask

@task('change_permissions', ['on' => ['web']])
php artisan optimize:clear
composer dump-autoload
php artisan cache:clear
php artisan route:clear
php artisan config:clear
php artisan view:clear
@endtask

@task('clean_old_releases', ['on' => ['web']])
    echo 'Clean releases'
    cd {{ $releases_dir }}
    ls -dt {{ $releases_dir }}/* | tail -n +3 | xargs -d "\n" rm -rf;

@endtask