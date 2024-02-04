<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function user_info_for_update($user_id) {
        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, email, phone
                FROM users WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'plot_id' => $row['plot_id'],
            ];
        } else {
            return [
                'id' => 0,
                'first_name' => '',
                'last_name' => '',
                'email' => '',
                'phone' => '',
                'plot_id' => '',
            ];
        }
    }

    public static function users_fetch($d = []) {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginatorUsers']];
    }

    public static function users_list($d = []) {
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        $where = [];

        if ($search) {
            $where[] = "first_name LIKE '%" . $search . "%'";
            $where[] = "last_name LIKE '%" . $search . "%'";
            $where[] = "phone LIKE '%" . $search . "%'";
            $where[] = "email LIKE '%" . $search . "%'";
        }

        $where = $where ? "WHERE " . implode(" OR ", $where) : "";

        $q = DB::query("SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users " . $where . " ORDER BY plot_id+0 LIMIT " . $offset . ", " . $limit . ";") or die(DB::error());

        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int) $row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => phone_formatting($row['phone']),
                'email' => (string) $row['email'],
                'last_login' => date('Y/m/d H:i:s', strtotime($row['last_login']))
            ];
        }

        // paginator
        $q_count = DB::query("SELECT count(*) FROM users " . $where . ";");
        $row_count = ($row = DB::fetch_row($q_count)) ? $row['count(*)'] : 0;

        $url = 'users?';

        if ($search) $url .= '&search=' . $search;
        paginator($row_count, $offset, $limit, $url, $paginatorUsers);

        // output
        return [
            'items' => $items,
            'paginatorUsers' => $paginatorUsers
        ];
    }

    // ACTIONS

    public static function user_edit_window($d = []) {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', User::user_info_for_update($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_delete($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        DB::query("DELETE FROM users WHERE user_id = '". $user_id ."';") or die(DB::error());

        return User::users_fetch(['offset' => $offset]);
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $plot_ids = isset($d['plot_id']) ? $d['plot_id'] : [null];
        $first_name = isset($d['first_name']) ? $d['first_name'] : '';
        $last_name = isset($d['last_name']) ? $d['last_name'] : '';
        $phone = isset($d['phone']) ? preg_replace('/\D/', '', $d['phone']) : 0;
        $email = isset($d['email']) ? strtolower($d['email']) : 0;
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        if ($user_id) {
            $set = "user_id='{$user_id}', plot_id='{$plot_ids}', first_name='{$first_name}', 
                    last_name='{$last_name}', phone='{$phone}', email='{$email}', updated='" . Session::$ts . "'";

            DB::query("UPDATE users SET " . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die(DB::error());

        } else {
            $values = "('{$user_id}', '{$plot_ids}', '{$first_name}', '{$last_name}',
                      '{$phone}', '{$email}', '" . Session::$ts . "')";

            DB::query("INSERT INTO users (user_id, plot_id, first_name, last_name, phone, email, updated) 
                       VALUES {$values};") or die(DB::error());

            return User::users_fetch(['offset' => $offset]);
        }

        return User::users_fetch(['offset' => $offset]);
    }
}