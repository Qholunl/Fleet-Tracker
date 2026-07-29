// ================================================================
// 📁 netlify/functions/index.js
// Netlify Function untuk Login & Register dengan Supabase
// ================================================================

const { createClient } = require('@supabase/supabase-js');

// ================================================================
// 1. KONFIGURASI SUPABASE (dari environment variables)
// ================================================================
const supabaseUrl = process.env.SUPABASE_URL;
const supabaseKey = process.env.SUPABASE_SERVICE_ROLE_KEY; // Gunakan service_role key untuk backend

if (!supabaseUrl || !supabaseKey) {
    throw new Error('Missing SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY in environment');
}

const supabase = createClient(supabaseUrl, supabaseKey);

// ================================================================
// 2. HANDLER UTAMA
// ================================================================
exports.handler = async (event, context) => {
    // Hanya menerima POST
    if (event.httpMethod !== 'POST') {
        return {
            statusCode: 405,
            body: JSON.stringify({ error: 'Method not allowed' })
        };
    }

    // Parse path untuk menentukan endpoint
    const path = event.path.replace(/^\/\.netlify\/functions\/index/, '').replace(/^\/api/, '');
    const endpoint = path || event.queryStringParameters?.endpoint;

    try {
        const body = JSON.parse(event.body);

        // ============================================================
        // ENDPOINT: /register
        // ============================================================
        if (endpoint === 'register' || event.path.includes('register')) {
            return await handleRegister(body);
        }

        // ============================================================
        // ENDPOINT: /login
        // ============================================================
        if (endpoint === 'login' || event.path.includes('login')) {
            return await handleLogin(body);
        }

        // ============================================================
        // ENDPOINT: /logout (opsional)
        // ============================================================
        if (endpoint === 'logout') {
            return {
                statusCode: 200,
                body: JSON.stringify({ status: 'success', message: 'Logged out' })
            };
        }

        // ============================================================
        // Default: 404
        // ============================================================
        return {
            statusCode: 404,
            body: JSON.stringify({ error: 'Endpoint not found' })
        };

    } catch (error) {
        console.error('Handler error:', error);
        return {
            statusCode: 400,
            body: JSON.stringify({ error: 'Invalid request body' })
        };
    }
};

// ================================================================
// 3. FUNGSI REGISTER
// ================================================================
async function handleRegister(body) {
    const { name, email, username, password } = body;

    // Validasi
    if (!name || !email || !username || !password) {
        return {
            statusCode: 400,
            body: JSON.stringify({ status: 'error', message: 'Semua field wajib diisi!' })
        };
    }
    if (password.length < 6) {
        return {
            statusCode: 400,
            body: JSON.stringify({ status: 'error', message: 'Password minimal 6 karakter!' })
        };
    }

    try {
        // 1. Daftarkan user ke Supabase Auth
        const { data: authData, error: authError } = await supabase.auth.admin.createUser({
            email: email,
            password: password,
            email_confirm: true, // Auto-confirm untuk testing
            user_metadata: {
                name: name,
                username: username
            }
        });

        if (authError) {
            // Jika email sudah terdaftar, error akan muncul
            return {
                statusCode: 400,
                body: JSON.stringify({ status: 'error', message: authError.message })
            };
        }

        const userId = authData.user.id;

        // 2. Profile sudah otomatis dibuat oleh trigger di Supabase
        //    Tapi kita bisa update jika diperlukan (misal tambahkan username)
        const { error: profileError } = await supabase
            .from('profiles')
            .update({ name, username })
            .eq('user_id', userId);

        if (profileError) {
            // Profile gagal diupdate, tapi user sudah terdaftar
            console.warn('Profile update error:', profileError);
        }

        // 3. Buat default settings untuk user baru
        const { error: settingsError } = await supabase
            .from('settings')
            .insert([{ user_id: userId }]);

        if (settingsError) {
            console.warn('Settings creation error:', settingsError);
        }

        return {
            statusCode: 200,
            body: JSON.stringify({
                status: 'success',
                message: 'Registrasi berhasil!',
                user_id: userId,
                name: name,
                username: username,
                email: email
            })
        };

    } catch (error) {
        console.error('Register error:', error);
        return {
            statusCode: 500,
            body: JSON.stringify({ status: 'error', message: 'Internal server error' })
        };
    }
}

// ================================================================
// 4. FUNGSI LOGIN
// ================================================================
async function handleLogin(body) {
    const { email, password } = body;

    if (!email || !password) {
        return {
            statusCode: 400,
            body: JSON.stringify({ status: 'error', message: 'Email dan password wajib diisi!' })
        };
    }

    try {
        // Login dengan Supabase Auth
        const { data, error } = await supabase.auth.signInWithPassword({
            email: email,
            password: password
        });

        if (error) {
            return {
                statusCode: 401,
                body: JSON.stringify({ status: 'error', message: error.message || 'Email atau password salah' })
            };
        }

        const user = data.user;

        // Ambil data profile dari tabel profiles
        const { data: profileData, error: profileError } = await supabase
            .from('profiles')
            .select('name, username')
            .eq('user_id', user.id)
            .single();

        if (profileError) {
            console.warn('Profile fetch error:', profileError);
        }

        return {
            statusCode: 200,
            body: JSON.stringify({
                status: 'success',
                message: 'Login berhasil!',
                user_id: user.id,
                name: profileData?.name || user.user_metadata?.name || email.split('@')[0],
                username: profileData?.username || user.user_metadata?.username || email.split('@')[0],
                email: user.email,
                access_token: data.session?.access_token, // Optional, untuk frontend
            })
        };

    } catch (error) {
        console.error('Login error:', error);
        return {
            statusCode: 500,
            body: JSON.stringify({ status: 'error', message: 'Internal server error' })
        };
    }
}