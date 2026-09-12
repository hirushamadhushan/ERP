<?php

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $username = '';
    public $password = '';
    public $remember = false;

    public function login()
    {
        $this->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $inputUsername = trim($this->username);
        $inputPassword = $this->password;

        // Verify the supplied username or email against the stored password hash.
        $credentials = filter_var($inputUsername, FILTER_VALIDATE_EMAIL)
            ? ['email' => $inputUsername, 'password' => $inputPassword]
            : ['username' => $inputUsername, 'password' => $inputPassword];

        if (Auth::attempt($credentials, $this->remember)) {
            session()->regenerate();

            return redirect()->intended('/home');
        }

        $this->addError('username', 'The username or password you entered is incorrect.');
    }
};
?>

<div class="bg-white rounded-3xl p-8 sm:p-10 shadow-xl shadow-purple-900/10 border border-purple-100 transition-all duration-300" x-data="{ showPassword: false }">
    
    <!-- Header / Brand Logo -->
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl text-white shadow-lg shadow-purple-500/30 mb-4 transform hover:scale-105 transition-transform duration-200" style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);">
            <svg width="28" height="28" class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-slate-900 tracking-tight">Nexus ERP</h1>
        <p class="text-xs text-slate-500 mt-1 font-medium">Welcome back! Sign in to access your dashboard</p>
    </div>

    <!-- Error Alert -->
    @if ($errors->has('username'))
        <div class="mb-6 p-3.5 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2.5">
            <svg width="18" height="18" class="w-4 h-4 text-rose-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span class="font-medium">{{ $errors->first('username') }}</span>
        </div>
    @endif

    <!-- Login Form -->
    <form wire:submit="login" class="space-y-4">
        
        <!-- Username / Email Input -->
        <div>
            <label for="username" class="block text-xs font-semibold text-slate-700 mb-1.5">
                Username or Email
            </label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg width="18" height="18" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                    </svg>
                </div>
                <input 
                    type="text" 
                    id="username" 
                    wire:model="username" 
                    placeholder="Username or email address" 
                    class="w-full pl-10 pr-4 py-3 bg-slate-50 border @error('username') border-rose-300 focus:ring-rose-400 @else border-slate-200 focus:border-purple-500 focus:ring-purple-500/20 @enderror rounded-2xl text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-4 focus:bg-white transition-all duration-200"
                    required 
                    autofocus 
                />
            </div>
        </div>

        <!-- Password Input -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-xs font-semibold text-slate-700">
                    Password
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs text-purple-600 hover:text-purple-700 font-semibold transition-colors">
                        Forgot?
                    </a>
                @endif
            </div>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg width="18" height="18" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                </div>
                <input 
                    :type="showPassword ? 'text' : 'password'" 
                    id="password" 
                    wire:model="password" 
                    placeholder="••••••••••••" 
                    class="w-full pl-10 pr-10 py-3 bg-slate-50 border @error('password') border-rose-300 focus:ring-rose-400 @else border-slate-200 focus:border-purple-500 focus:ring-purple-500/20 @enderror rounded-2xl text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-4 focus:bg-white transition-all duration-200"
                    required 
                />
                <button 
                    type="button" 
                    @click="showPassword = !showPassword" 
                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none transition-colors"
                >
                    <template x-if="!showPassword">
                        <svg width="18" height="18" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </template>
                    <template x-if="showPassword">
                        <svg width="18" height="18" class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858-5.908a8.959 8.959 0 014.122-.963c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m-0.469 0.469L3 3l18 18"></path>
                        </svg>
                    </template>
                </button>
            </div>
            @error('password')
                <p class="mt-1 text-xs text-rose-500 font-medium">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer group">
                <input 
                    type="checkbox" 
                    wire:model="remember" 
                    class="w-4 h-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500 cursor-pointer transition-colors"
                />
                <span class="text-xs text-slate-600 group-hover:text-slate-900 transition-colors">
                    Remember me
                </span>
            </label>
        </div>

        <!-- Submit Button -->
        <div class="pt-2">
            <button 
                type="submit" 
                class="w-full relative group flex items-center justify-center py-3.5 px-4 rounded-2xl text-sm font-semibold text-white focus:outline-none focus:ring-4 focus:ring-purple-500/20 shadow-md shadow-purple-500/20 hover:shadow-lg hover:shadow-purple-500/30 transition-all duration-200 transform active:scale-[0.99]"
                style="background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);"
                wire:loading.attr="disabled"
            >
                <span wire:loading.remove wire:target="login" class="flex items-center gap-2">
                    Sign In
                    <svg width="16" height="16" class="w-4 h-4 text-white transition-transform group-hover:translate-x-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                    </svg>
                </span>

                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg width="16" height="16" class="animate-spin h-4 w-4 text-white shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Signing In...
                </span>
            </button>
        </div>
    </form>

    <!-- Footer -->
    <div class="mt-8 text-center text-xs text-slate-400">
        &copy; {{ date('Y') }} Nexus ERP • All rights reserved
    </div>

</div>
