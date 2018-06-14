<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Users extends Admin_Controller
{

    public function __construct()
    {
        parent::__construct();

        /* Load :: Common */
        $this->lang->load('admin/users');

        /* Load models */
        $this->load->model('registration_model');
        $this->load->model('groups_model');
        $this->load->model('cart_model');
        $this->load->model('images_model');

        /* Title Page :: Common */
        $this->page_title->push(lang('menu_users'));
        $this->data['pagetitle'] = $this->page_title->show();

        /* Breadcrumbs :: Common */
        $this->breadcrumbs->unshift(1, lang('menu_users'), 'admin/users');
    }


    public function index()
    {
//        if (!$this->ion_auth->logged_in() OR !$this->ion_auth->is_admin()) {
//            redirect('auth/login', 'refresh');
//            return;
//        }


        /* Breadcrumbs */
        $this->data['breadcrumb'] = $this->breadcrumbs->show();

        /* Get all users */
//        $this->data['users'] = $this->ion_auth->users()->result();
        $this->data['users'] = $this->db->query("select * from users order by id")->result();

        foreach ($this->data['users'] as $k => $user) {
            $this->data['users'][$k]->groups = $this->ion_auth->get_users_groups($user->id)->result();
        }

        /* Load Template */
        $this->template->admin_render('admin/users/index', $this->data);

    }


    public function create()
    {
        /* Breadcrumbs */
        $this->breadcrumbs->unshift(2, lang('menu_users_create'), 'admin/users/create');
        $this->data['breadcrumb'] = $this->breadcrumbs->show();

        /* Variables */
        $tables = $this->config->item('tables', 'ion_auth');

        $groups = $this->groups_model->get();

        /* Validate form input */
        $this->form_validation->set_rules('first_name', 'lang:users_firstname', 'required');
        $this->form_validation->set_rules('last_name', 'lang:users_lastname', 'required');
        $this->form_validation->set_rules('email', 'lang:users_email',
            'required|valid_email|is_unique[' . $tables['users'] . '.email]');
        $this->form_validation->set_rules('group', 'Group', 'required');
        $this->form_validation->set_rules('password', 'lang:users_password',
            'required|min_length[' . $this->config->item('min_password_length',
                'ion_auth') . ']|max_length[' . $this->config->item('max_password_length',
                'ion_auth') . ']|matches[password_confirm]');
        $this->form_validation->set_rules('password_confirm', 'lang:users_password_confirm', 'required');

        if ($this->form_validation->run() == true) {
            $email = strtolower($this->input->post('email'));
            $password = $this->input->post('password');

            $group = $this->input->post('group');

            $user = [
                'email' => $email,
                'password' => $password,
                'username' => $this->input->post('first_name') . ' ' . $this->input->post('last_name')
            ];
        }

        if ($this->form_validation->run() == true && $this->registration_model->register($user, $group)) {
//            $this->session->set_flashdata('message', $this->ion_auth->messages());
            redirect('admin/users', 'refresh');
        } else {
            $this->data['message'] = (validation_errors() ? validation_errors() : ($this->registration_model->errors() ? $this->registration_model->errors() : $this->session->flashdata('message')));

            $this->data['options'] = array(
                '#' => null,
            );

            foreach ($groups as $key => $value) {
                $this->data['options'][$value->id] = $value->name;
                //array_push($this->data['options'], ($value['id'] => $value['name']) );
            }

            $this->data['first_name'] = array(
                'name' => 'first_name',
                'id' => 'first_name',
                'type' => 'text',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('first_name'),
            );
            $this->data['last_name'] = array(
                'name' => 'last_name',
                'id' => 'last_name',
                'type' => 'text',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('last_name'),
            );
            $this->data['email'] = array(
                'name' => 'email',
                'id' => 'email',
                'type' => 'email',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('email'),
            );
            $this->data['group'] = array(
                'name' => 'group',
                'id' => 'group',
                'type' => 'text',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('group'),
            );

            $this->data['password'] = array(
                'name' => 'password',
                'id' => 'password',
                'type' => 'password',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('password'),
            );
            $this->data['password_confirm'] = array(
                'name' => 'password_confirm',
                'id' => 'password_confirm',
                'type' => 'password',
                'class' => 'form-control',
                'value' => $this->form_validation->set_value('password_confirm'),
            );

            /* Load Template */
            $this->template->admin_render('admin/users/create', $this->data);
        }
    }


    public function delete()
    {
        /* Load Template */
        $this->template->admin_render('admin/users/delete', $this->data);
    }


    public function edit($id)
    {
        $id = (int)$id;

        if (!$this->ion_auth->logged_in() OR (!$this->ion_auth->is_admin() && !($this->ion_auth->user()->row()->id == $id))) {
            redirect('auth', 'refresh');
        }


        /* Breadcrumbs */
        $this->breadcrumbs->unshift(2, lang('menu_users_edit'), 'admin/users/edit');
        $this->data['breadcrumb'] = $this->breadcrumbs->show();

        /* Data */
        $user = $this->ion_auth->user($id)->row();
        $groups = $this->ion_auth->groups()->result_array();
        $currentGroups = $this->ion_auth->get_users_groups($id)->result();

        /* Validate form input */
        $this->form_validation->set_rules('full_name', 'Full name', 'required');

        if (isset($_POST) && !empty($_POST)) {
            if ($this->_valid_csrf_nonce() === false OR $id != $this->input->post('id')) {
                show_error($this->lang->line('error_csrf'));
            }

            if ($this->input->post('password')) {
                $this->form_validation->set_rules('password', $this->lang->line('edit_user_validation_password_label'),
                    'required|min_length[' . $this->config->item('min_password_length',
                        'ion_auth') . ']|max_length[' . $this->config->item('max_password_length',
                        'ion_auth') . ']|matches[password_confirm]');
                $this->form_validation->set_rules('password_confirm',
                    $this->lang->line('edit_user_validation_password_confirm_label'), 'required');
            }

            if ($this->form_validation->run() == true) {
                $data = array(
                    'name' => $this->input->post('full_name')
                );

                if ($this->input->post('password')) {
                    $data['password'] = $this->input->post('password');
                }

                if ($this->ion_auth->is_admin()) {
                    $groupData = $this->input->post('groups');

                    if (isset($groupData) && !empty($groupData)) {
                        $this->ion_auth->remove_from_group('', $id);

                        foreach ($groupData as $grp) {
                            $this->ion_auth->add_to_group($grp, $id);
                        }
                    }
                }

                if ($this->ion_auth->update($user->id, $data)) {
                    $this->session->set_flashdata('message', $this->ion_auth->messages());

                    if ($this->ion_auth->is_admin()) {
                        redirect('admin/users', 'refresh');
                    } else {
                        redirect('admin', 'refresh');
                    }
                } else {
                    $this->session->set_flashdata('message', $this->ion_auth->errors());

                    if ($this->ion_auth->is_admin()) {
                        redirect('auth', 'refresh');
                    } else {
                        redirect('/', 'refresh');
                    }
                }
            }
        }

        // display the edit user form
        $this->data['csrf'] = $this->_get_csrf_nonce();

        // set the flash data error message if there is one
        $this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

        // pass the user to the view
        $this->data['user'] = $user;
        $this->data['groups'] = $groups;
        $this->data['currentGroups'] = $currentGroups;

        $this->data['full_name'] = array(
            'name' => 'full_name',
            'id' => 'full_name',
            'type' => 'text',
            'class' => 'form-control',
            'value' => $this->form_validation->set_value('full_name', $user->name)
        );
        $this->data['phone'] = array(
            'name' => 'phone',
            'id' => 'phone',
            'type' => 'tel',
            'pattern' => '^((\+\d{1,3}(-| )?\(?\d\)?(-| )?\d{1,5})|(\(?\d{2,6}\)?))(-| )?(\d{3,4})(-| )?(\d{4})(( x| ext)\d{1,5}){0,1}$',
            'class' => 'form-control'
        );
        $this->data['password'] = array(
            'name' => 'password',
            'id' => 'password',
            'class' => 'form-control',
            'type' => 'password'
        );
        $this->data['password_confirm'] = array(
            'name' => 'password_confirm',
            'id' => 'password_confirm',
            'class' => 'form-control',
            'type' => 'password'
        );


        /* Load Template */
        $this->template->admin_render('admin/users/edit', $this->data);
    }


    function activate($id, $code = false)
    {
        $id = (int)$id;

        if ($code !== false) {
            $activation = $this->ion_auth->activate($id, $code);
        } else {
            if ($this->ion_auth->is_admin()) {
                $activation = $this->ion_auth->activate($id);
            }
        }

        if ($activation) {
            $this->session->set_flashdata('message', $this->ion_auth->messages());
            redirect('admin/users', 'refresh');
        } else {
            $this->session->set_flashdata('message', $this->ion_auth->errors());
            redirect('auth/forgot_password', 'refresh');
        }
    }


    public function deactivate($id = null)
    {
        if (!$this->ion_auth->logged_in() OR !$this->ion_auth->is_admin()) {
            return show_error('You must be an administrator to view this page.');
        }

        /* Breadcrumbs */
        $this->breadcrumbs->unshift(2, lang('menu_users_deactivate'), 'admin/users/deactivate');
        $this->data['breadcrumb'] = $this->breadcrumbs->show();

        /* Validate form input */
        $this->form_validation->set_rules('confirm', 'lang:deactivate_validation_confirm_label', 'required');
        $this->form_validation->set_rules('id', 'lang:deactivate_validation_user_id_label', 'required|alpha_numeric');

        $id = (int)$id;

        if ($this->form_validation->run() === false) {
            $user = $this->ion_auth->user($id)->row();

            $this->data['csrf'] = $this->_get_csrf_nonce();
            $this->data['id'] = (int)$user->id;
            $this->data['name'] = !empty($user->name) ? htmlspecialchars($user->name, ENT_QUOTES,
                'UTF-8') : null;

            /* Load Template */
            $this->template->admin_render('admin/users/deactivate', $this->data);
        } else {
            if ($this->input->post('confirm') == 'yes') {
                if ($this->_valid_csrf_nonce() === false OR $id != $this->input->post('id')) {
                    show_error($this->lang->line('error_csrf'));
                }

                if ($this->ion_auth->logged_in() && $this->ion_auth->is_admin()) {
                    $this->ion_auth->deactivate($id);
                }
            }

            redirect('admin/users', 'refresh');
        }
    }


    public function profile($id)
    {
        /* Breadcrumbs */
        $this->breadcrumbs->unshift(2, lang('menu_users_profile'), 'admin/groups/profile');
        $this->data['breadcrumb'] = $this->breadcrumbs->show();

        /* Data */
        $id = (int)$id;

        $this->data['user_info'] = $this->ion_auth->user($id)->result();
        $this->data['items'] = $this->cart_model->getCheckout($id);
        $this->data['images'] = $this->images_model->get_main_checkout($id); //get product images from user Cart

        foreach ($this->data['user_info'] as $k => $user) {
            $this->data['user_info'][$k]->groups = $this->ion_auth->get_users_groups($user->id)->result();
        }

        /* Load Template */
        $this->template->admin_render('admin/users/profile', $this->data);
    }


    public function _get_csrf_nonce()
    {
        $this->load->helper('string');
        $key = random_string('alnum', 8);
        $value = random_string('alnum', 20);
        $this->session->set_flashdata('csrfkey', $key);
        $this->session->set_flashdata('csrfvalue', $value);

        return array($key => $value);
    }


    public function _valid_csrf_nonce()
    {
        if ($this->input->post($this->session->flashdata('csrfkey')) !== false && $this->input->post($this->session->flashdata('csrfkey')) == $this->session->flashdata('csrfvalue')) {
            return true;
        } else {
            return false;
        }
    }
}