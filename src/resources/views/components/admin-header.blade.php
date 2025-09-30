<header class="header">
    <div class="header__logo">
        <a href="{{ route('admin.attendance') }}">
            <img src="{{ asset('images/logo.svg') }}" alt="COACHTECH">
        </a>
    </div>
    @if( !in_array(Route::currentRouteName(), ['admin.login', 'admin.login.store']) )
    <nav class="header__nav">
        <ul>
            @if(Auth::guard('admin')->check())
                <li><a href="{{ route('admin.attendance') }}" class="header__link">勤怠一覧</a></li>
                <li><a href="/admin/staff/list" class="header__link">スタッフ一覧</a></li>
                <li><a href="{{ route('request.index', ['as' => 'admin']) }}" class="header__link">申請一覧</a></li>
                <li>
                    <form action="{{ route('admin.logout') }}" method="post">
                        @csrf
                        <button class="header__link header__logout">ログアウト</button>
                    </form>
                </li>
            @endif
        </ul>
    </nav>
    @endif
</header>