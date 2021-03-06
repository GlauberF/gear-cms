<?php

class application {

    private $class = false;

    public $admin = false;

    private $controller = false;
    private $action = false;
    private $params = [];

    public static $url;

    public function __construct($env) {

        $this->splitUrl();

        if(config::get('install', false) && $env != 'install') {
            header('location: '.url::base(['install']));
            exit();
        }

        if($env == 'admin') {

            $this->admin = true;

            if(config::get('dev') && !ajax::is()) {
                admin::generateLess();
            }

            $this->bootstrap();
            $this->admin();

        } elseif($env == 'install') {

            if(!file_exists(dir::css('style.css'))) {
                admin::generateLess();
            }

            $this->install();

        } else {

            $this->bootstrap();
            $this->frontend();

        }

    }

    private function bootstrap() {
        plugin::getIncludes();
        theme::getIncludes();
    }

    private function install() {

        $steps = [
            'language',
            'database',
            'informations',
            'finished'
        ];

        $step = type::super('step', 'string', 'language');

        include(dir::install('view/index.php'));

    }

    private function frontend() {

        ob_start();

        $page = (new PageModel())->getByURL(self::getUrl());

        visit::add();

        $cache = (config::get('cache')) ? dir::cache() : false;

        $loader = new Twig_Loader_Filesystem(dir::themes(option::get('theme')));
        $twig = new Twig_Environment($loader, [
            'cache' => $cache
        ]);

        $templateFile = 'index.php';

        if($page && $page->template && file_exists(dir::themes(option::get('theme'), $page->template))) {
            $templateFile = $page->template;
        }

        $template = $twig->load($templateFile);

        if(!$page) {
            echo '<h1>404</h1>';
        } else {
            echo '<h1>'.$page->name.'</h1>';
            var_dump($page);
        }

        $content = ob_get_contents();
        ob_end_clean();

        echo $template->render([
            'content' => $content
        ]);

    }

    private function admin() {
        if(user::loggedIn()) {

            $controller = $this->controller;

            $this->class = $controller.'Controller';

            if(!$controller) {
                $this->error404();
            } elseif(file_exists(dir::controller($this->controller.'.php'))) {

                include(dir::controller($this->controller.'.php'));

                $this->class = new $this->class();

                if(method_exists($this->class, $this->action)) {

                    if(!empty($this->params)) {

                        if(user::hasPerm($this->controller.'['.$this->action.']['.$this->params[0].']')) {
                            call_user_func_array([$this->class, $this->action], $this->params);
                        } else {
                            $this->permissionDenied();
                        }

                    } else {

                        if(user::hasPerm($this->controller.'['.$this->action.']')) {
                            $this->class->{$this->action}();
                        } else {
                            $this->permissionDenied();
                        }

                    }

                } else {

                    if(strlen($this->action) == 0) {

                        header('location: '.url::admin($this->controller, ['index']));
                        exit();

                    } else {
                        $this->error404();
                    }

                }

            } else {
                $this->error404();
            }

        } else {

            include(dir::controller('login.php'));

            $page = new loginController();
            $page->index();

        }
    }

    public function error404() {

        $path = ($this->admin) ? url::admin('dashboard') : '';

        if(!ajax::is()) {
            header('location: '.$path);
            exit();
        }

    }

    public function permissionDenied() {

        echo message::getMessage(lang::get('permission_denied'), 'error');

    }

    private function splitUrl() {

        if(type::get('url', 'string', false)) {

            $url = self::getUrl();

            $this->controller = isset($url[0]) ? $url[0] : '';
            $this->action = isset($url[1]) ? $url[1] : '';

            unset($url[0], $url[1]);

            $delete = type::get('delete', 'string', '');

            if($delete) {
                $url[0] = 'delete';
                $url[1] = $delete;
            }

            $this->params = (is_array($url)) ? array_values($url) : false;

        }

    }

    public static function getUrl() {

        if(type::get('url', 'string', false)) {

            $url = trim(type::get('url'), '/');
            $url = filter_var($url, FILTER_SANITIZE_URL);

            return explode('/', $url);

        }

        return false;

    }

}

?>
