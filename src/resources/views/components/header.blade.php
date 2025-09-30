<header class="header">
    <div class="header__logo">
        <a href="/attendance">
            <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH">
        </a>
    </div>
    @if( !in_array(Route::currentRouteName(), ['register', 'login', 'verification.notice']) )
    <nav class="header__nav">
        <ul>
            @if(Auth::check())
            <li><a href="/attendance" class="header__link">勤怠</a></li>
            <li><a href="/attendance/list" class="header__link">勤怠一覧</a></li>
            <li><a href="{{ route('request.index', ['as' => 'user']) }}" class="header__link">申請</a></li>
            <li>
                <form action="{{ route('logout') }}" method="post">
                    @csrf
                    <button class="header__link header__logout">ログアウト</button>
                </form>
            </li>
            @endif
        </ul>
    </nav>
    @endif
</header>