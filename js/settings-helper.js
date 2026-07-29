// js/settings-helper.js
// ============================================================
// Helper untuk mengelola pengaturan dari database & localStorage
// ============================================================

const SETTINGS_API = window.location.origin + '/api/settings.php';
const STORAGE_KEY = 'fleet_settings';

const DEFAULT_SETTINGS = {
    fullName: '',
    email: '',
    phone: '+6285646839016',
    timezone: 'Asia/Jakarta',
    prefLang: 'id',
    avatar: null,
    twofa: false,
    speedLimit: 80,
    serviceInterval: 5000,
    solenoidLock: true,
    fuelDropThreshold: 5,
    notifWhatsapp: true,
    notifSound: false,
    notifEmail: false,
    alertSpeeding: true,
    alertGeofence: true,
    alertFuelTheft: true,
    alertOffline: true,
    apiBase: 'https://fleet-tracker.wuaze.com/api/',
    baudRate: '115200',
    pollingInterval: 5,
    webhookUrl: '',
    jsonPayload: `{\n    "device_id": "{device_id}",\n    "lat": {lat},\n    "lng": {lng},\n    "speed": {speed},\n    "fuel": {fuel},\n    "engine": {engine_status}\n}`
};

let cachedSettings = null;
let settingsLoaded = false;

// Ambil user dari localStorage
function getCurrentUser() {
    try {
        const data = localStorage.getItem('fleet_current_user');
        return data ? JSON.parse(data) : null;
    } catch { return null; }
}

// Load settings dari server + localStorage
async function loadSettings(force = false) {
    if (settingsLoaded && !force && cachedSettings) {
        return cachedSettings;
    }
    let settings = { ...DEFAULT_SETTINGS };
    const user = getCurrentUser();
    if (user && user.id) {
        try {
            const res = await fetch(`${SETTINGS_API}?user_id=${user.id}`);
            if (res.ok) {
                const data = await res.json();
                if (data.status !== 'not_found') {
                    settings = { ...DEFAULT_SETTINGS, ...data };
                }
            }
        } catch (e) {
            console.warn('Gagal load settings dari server:', e);
        }
    }
    // Override dengan localStorage (prioritas lokal)
    try {
        const stored = localStorage.getItem(STORAGE_KEY);
        if (stored) {
            const parsed = JSON.parse(stored);
            settings = { ...settings, ...parsed };
        }
    } catch (e) {}
    cachedSettings = settings;
    settingsLoaded = true;
    return settings;
}

// Simpan settings ke server dan localStorage
async function saveSettings(settings) {
    // Simpan ke lokal dulu
    localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    const user = getCurrentUser();
    if (user && user.id) {
        try {
            const res = await fetch(`${SETTINGS_API}?user_id=${user.id}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(settings)
            });
            const result = await res.json();
            if (result.status === 'success') {
                return { success: true, message: 'Saved to server' };
            }
            return { success: false, message: result.message || 'Failed to save to server' };
        } catch (e) {
            return { success: false, message: 'Saved locally only' };
        }
    }
    return { success: true, message: 'Saved locally' };
}

// Ambil satu nilai setting
async function getSetting(key) {
    const settings = await loadSettings();
    return settings[key] ?? DEFAULT_SETTINGS[key];
}

// Terapkan settings ke UI (override variabel global & tampilan)
function applySettingsToUI(settings) {
    // Override global variables (untuk dashboard, history, dll)
    if (typeof window.speedLimit !== 'undefined') window.speedLimit = settings.speedLimit || 80;
    if (typeof window.serviceInterval !== 'undefined') window.serviceInterval = settings.serviceInterval || 5000;
    if (typeof window.solenoidLock !== 'undefined') window.solenoidLock = settings.solenoidLock !== false;
    if (typeof window.fuelDropThreshold !== 'undefined') window.fuelDropThreshold = settings.fuelDropThreshold || 5;
    if (typeof window.API_BASE !== 'undefined') window.API_BASE = settings.apiBase || 'https://fleet-tracker.wuaze.com/api/';
    if (typeof window.pollingInterval !== 'undefined') window.pollingInterval = settings.pollingInterval || 5;
    if (typeof window.notifSettings !== 'undefined') {
        window.notifSettings = {
            whatsapp: settings.notifWhatsapp !== false,
            sound: settings.notifSound || false,
            email: settings.notifEmail || false,
            alerts: {
                speeding: settings.alertSpeeding !== false,
                geofence: settings.alertGeofence !== false,
                fuelTheft: settings.alertFuelTheft !== false,
                offline: settings.alertOffline !== false
            }
        };
    }
    // Update username di header
    const nameEl = document.getElementById('userName');
    if (nameEl) nameEl.textContent = settings.fullName || 'User';
    // Update bahasa (jika ada fungsi changeLanguage)
    const lang = settings.prefLang || 'id';
    if (typeof changeLanguage === 'function') {
        changeLanguage(lang);
    }
    // Update avatar jika ada elemen avatar
    const avatarImg = document.querySelector('.avatar-preview img');
    if (avatarImg && settings.avatar) {
        avatarImg.src = settings.avatar;
    }
}