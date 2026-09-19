<?php
/**
 * TaskFlow — Locale & RTL support.
 * Arabic is opt-in per user session; English remains the default.
 */
function supported_locales(): array {
    return ['en' => 'English', 'ar' => 'العربية'];
}
function locale(): string {
    $value = (string)($_SESSION['locale'] ?? 'en');
    return array_key_exists($value, supported_locales()) ? $value : 'en';
}
function is_rtl(): bool { return locale() === 'ar'; }
function set_locale(string $value): string {
    $value = array_key_exists($value, supported_locales()) ? $value : 'en';
    $_SESSION['locale'] = $value;
    return $value;
}
function locale_translations(): array {
    static $ar = [
        'Workspace'=>'مساحة العمل','Insights'=>'التقارير والتحليلات','Administration'=>'الإدارة',
        'Dashboard'=>'لوحة التحكم','Task Board'=>'لوحة المهام','All Tasks'=>'كل المهام','Projects'=>'المشاريع',
        'Team'=>'الفريق','Departments'=>'الأقسام','Reports'=>'التقارير','Activity Log'=>'سجل النشاط',
        'UX Audit'=>'تدقيق تجربة المستخدم','Roles & Permissions'=>'الأدوار والصلاحيات','Settings'=>'الإعدادات',
        'New task'=>'مهمة جديدة','Notifications'=>'الإشعارات','Mark all read'=>'تحديد الكل كمقروء',
        'View all notifications'=>'عرض كل الإشعارات','My profile'=>'ملفي الشخصي','My tasks'=>'مهامي',
        'Sign out'=>'تسجيل الخروج','Toggle navigation'=>'تبديل التنقل','Toggle dark mode'=>'تبديل الوضع الداكن',
        'Account menu'=>'قائمة الحساب','Search tasks, projects, people…'=>'ابحث في المهام والمشاريع والأشخاص…',
        'Loading…'=>'جارٍ التحميل…','Close'=>'إغلاق','Title'=>'العنوان','Save profile'=>'حفظ الملف الشخصي',
        'Personal information'=>'المعلومات الشخصية','Change password'=>'تغيير كلمة المرور','Full name'=>'الاسم الكامل',
        'Email address'=>'البريد الإلكتروني','Job title'=>'المسمى الوظيفي','Phone'=>'الهاتف',
        'Avatar colour'=>'لون الصورة الرمزية','Change picture'=>'تغيير الصورة','Save'=>'حفظ',
        'Language'=>'اللغة','English'=>'الإنجليزية','Arabic'=>'العربية','Your avatar is shown on tasks, comments and notifications.'=>'تظهر صورتك الرمزية في المهام والتعليقات والإشعارات.',
        'Send me an e-mail when I am assigned a task or mentioned in a comment'=>'أرسل لي بريدًا إلكترونيًا عند إسناد مهمة إليّ أو ذكري في تعليق',
        'Active'=>'نشط','Inactive'=>'غير نشط','Suspended'=>'موقوف','All'=>'الكل','Apply'=>'تطبيق','Reset'=>'إعادة ضبط',
        'Add member'=>'إضافة عضو','Export'=>'تصدير','Member'=>'العضو','Department'=>'القسم','Role'=>'الدور',
        'Projects'=>'المشاريع','Open'=>'المفتوحة','Done'=>'المكتملة','Hours'=>'الساعات','Last seen'=>'آخر ظهور',
        'Profile'=>'الملف الشخصي','Edit member'=>'تعديل العضو','Reset password'=>'إعادة تعيين كلمة المرور',
        'Search by name, email or job title…'=>'ابحث بالاسم أو البريد أو المسمى الوظيفي…',
        'All departments'=>'كل الأقسام','All roles'=>'كل الأدوار','Sort: Name'=>'ترتيب: الاسم',
        'Sort: Open tasks'=>'ترتيب: المهام المفتوحة','Sort: Completed'=>'ترتيب: المكتملة',
        'Sort: Hours logged'=>'ترتيب: الساعات المسجلة','Sort: Department'=>'ترتيب: القسم','Sort: Newest'=>'ترتيب: الأحدث',
        'No team members found'=>'لم يتم العثور على أعضاء','Adjust the filters, or add a new member to your company.'=>'عدّل عوامل التصفية أو أضف عضوًا جديدًا إلى شركتك.',
        'Company'=>'الشركة','System'=>'النظام','Data & backup'=>'البيانات والنسخ الاحتياطي',
        'Company name'=>'اسم الشركة','Contact e-mail'=>'البريد الإلكتروني للتواصل','Address'=>'العنوان',
        'Save company settings'=>'حفظ إعدادات الشركة','System preferences'=>'تفضيلات النظام','Default theme'=>'السمة الافتراضية',
        'Light'=>'فاتح','Dark'=>'داكن','Items per page'=>'العناصر في الصفحة','Database contents'=>'محتويات قاعدة البيانات',
        'Database driver'=>'محرك قاعدة البيانات','Member permissions'=>'صلاحيات العضو','Supervisor'=>'المشرف',
        'Team Lead'=>'قائد الفريق','Member'=>'عضو','Viewer'=>'مشاهد','Manager'=>'مدير','Super Admin'=>'مدير النظام',
        'Permissions'=>'الصلاحيات','Create role'=>'إنشاء دور','Create'=>'إنشاء','Update'=>'تحديث','Delete'=>'حذف',
        'Restore default permissions'=>'استعادة الصلاحيات الافتراضية','Restore defaults'=>'استعادة الافتراضيات','Select all'=>'تحديد الكل','Clear'=>'مسح','Toggle module'=>'تبديل الوحدة',
        'Module / permission'=>'الوحدة / الصلاحية','Members with this role'=>'الأعضاء بهذا الدور','New role'=>'دور جديد','Edit role'=>'تعديل الدور',
        'No roles defined'=>'لا توجد أدوار معرفة','Create a role to start granting permissions.'=>'أنشئ دورًا لبدء منح الصلاحيات.','No description.'=>'لا يوجد وصف.','permissions granted'=>'صلاحيات ممنوحة',
        'This role always has full access'=>'هذا الدور لديه وصول كامل دائمًا','this role always has full access'=>'هذا الدور لديه وصول كامل دائمًا','Built-in'=>'مدمج','Granted'=>'ممنوحة','selected'=>'محدد',
        'Manager'=>'المدير','Employee'=>'الموظف','Member'=>'العضو','Supervisor'=>'المشرف','Team Lead'=>'قائد الفريق','Viewer'=>'مشاهد','Super Admin'=>'مدير النظام',
        'Restore the recommended permissions for this built-in role'=>'استعادة الصلاحيات الموصى بها لهذا الدور المدمج',
        'What is this role responsible for?'=>'ما مسؤوليات هذا الدور؟','New roles start with the same permissions as'=>'تبدأ الأدوار الجديدة بنفس صلاحيات',

        'Cancel'=>'إلغاء','Description'=>'الوصف','Name'=>'الاسم','Save permissions'=>'حفظ الصلاحيات',
        'Built-in roles cannot be deleted.'=>'لا يمكن حذف الأدوار المدمجة.',
        'Skip to content'=>'تخطي إلى المحتوى','Toggle navigation'=>'تبديل التنقل','Toggle dark mode'=>'تبديل الوضع الداكن','Account menu'=>'قائمة الحساب',
        'Access denied'=>'تم رفض الوصول','Page not found'=>'الصفحة غير موجودة','Back to dashboard'=>'العودة إلى لوحة التحكم','Sign in'=>'تسجيل الدخول','Welcome back'=>'مرحبًا بعودتك','Sign in to your workspace to continue.'=>'سجّل الدخول إلى مساحة عملك للمتابعة.','Keep me signed in'=>'إبقائي مسجّلًا للدخول','Need an account?'=>'ليس لديك حساب؟','Create one'=>'إنشاء حساب','Demo accounts'=>'حسابات تجريبية','Fill admin credentials'=>'تعبئة بيانات دخول المدير','No accounts yet — import'=>'لا توجد حسابات بعد — استورد','Every task. Every teammate.'=>'كل مهمة. كل زميل.','One clear picture.'=>'رؤية واضحة واحدة.','Plan projects, assign work, track progress in real time and keep the whole company aligned — the way the best teams operate.'=>'خطط للمشاريع، وأسند العمل، وتابع التقدم لحظيًا، وحافظ على توافق فريق الشركة بالكامل.','Kanban boards with drag & drop'=>'لوحات كانبان بالسحب والإفلات','Fine-grained roles & permissions'=>'أدوار وصلاحيات دقيقة','Workload, velocity and productivity reports'=>'تقارير عبء العمل والسرعة والإنتاجية','Mentions, attachments and full audit trail'=>'الإشارات والمرفقات وسجل التدقيق الكامل',
        'Personal information'=>'المعلومات الشخصية','Your avatar is shown on tasks, comments and notifications.'=>'تظهر صورتك الرمزية في المهام والتعليقات والإشعارات.','Change picture'=>'تغيير الصورة','Send me an e-mail when I am assigned a task or mentioned in a comment'=>'أرسل لي بريدًا إلكترونيًا عند إسناد مهمة إليّ أو ذكري في تعليق','Current password'=>'كلمة المرور الحالية','New password'=>'كلمة المرور الجديدة','Confirm new password'=>'تأكيد كلمة المرور الجديدة','Changing your password signs you out of every other device.'=>'سيؤدي تغيير كلمة المرور إلى تسجيل خروجك من جميع الأجهزة الأخرى.','Update password'=>'تحديث كلمة المرور','Account'=>'الحساب','granted'=>'ممنوحة','Data scope'=>'نطاق البيانات','Member since'=>'عضو منذ','Last sign-in'=>'آخر تسجيل دخول','Session IP'=>'عنوان IP للجلسة','My activity'=>'نشاطي','Company profile'=>'ملف الشركة','Shown in the sidebar, on the login screen and in e-mails.'=>'يظهر في الشريط الجانبي وشاشة تسجيل الدخول ورسائل البريد الإلكتروني.','Logo'=>'الشعار','PNG, JPG, SVG or WebP. Recommended 200×60 px, transparent background.'=>'PNG أو JPG أو SVG أو WebP. المقاس الموصى به 200×60 بكسل، بخلفية شفافة.','Save company settings'=>'حفظ إعدادات الشركة','System preferences'=>'تفضيلات النظام','Your server also limits uploads via'=>'يحد خادمك أيضًا من الرفع عبر','E-mail notifications'=>'إشعارات البريد الإلكتروني','Enable e-mail notifications (uses PHP'=>'تفعيل إشعارات البريد الإلكتروني (تستخدم PHP','Allow people to create their own account from the login page'=>'السماح للأشخاص بإنشاء حساباتهم من صفحة تسجيل الدخول','New self-registered accounts receive the'=>'تحصل الحسابات الجديدة المسجلة ذاتيًا على دور','Keep this off for internal company use.'=>'اترك هذا الخيار معطلًا للاستخدام الداخلي للشركة.','Database contents'=>'محتويات قاعدة البيانات','Uploaded files on disk'=>'الملفات المرفوعة على القرص','Database driver'=>'محرك قاعدة البيانات','Download your data as CSV. Exports respect the current permission scope.'=>'نزّل بياناتك بصيغة CSV. تحترم عمليات التصدير نطاق الصلاحيات الحالي.','Team performance'=>'أداء الفريق','Activity log'=>'سجل النشاط','Maintenance'=>'الصيانة','Sensitive'=>'حساس','Choose an image'=>'اختر صورة','Square images look best. Max'=>'تظهر الصور المربعة بشكل أفضل. الحد الأقصى','Upload'=>'رفع','Save'=>'حفظ','Save system settings'=>'حفظ إعدادات النظام',
        'New task'=>'مهمة جديدة','Task title'=>'عنوان المهمة','Project'=>'المشروع','Select a project…'=>'اختر مشروعًا…','Assignee'=>'المسند إليه','Unassigned'=>'غير مسندة','Description'=>'الوصف','Priority'=>'الأولوية','Due date'=>'تاريخ الاستحقاق','Estimate (hours)'=>'التقدير (بالساعات)','Start date'=>'تاريخ البدء','Parent task (optional)'=>'المهمة الرئيسية (اختياري)','None — this is a top-level task'=>'لا يوجد — هذه مهمة رئيسية','Create task'=>'إنشاء مهمة','Project name'=>'اسم المشروع','Short code'=>'الرمز المختصر','Used as the task key prefix (e.g. PORTAL-12).'=>'يُستخدم كبادئة لمفتاح المهمة (مثل PORTAL-12).','Status'=>'الحالة','None'=>'لا يوجد','Public — everyone can see it'=>'عام — يمكن للجميع رؤيته','Private — members only'=>'خاص — للأعضاء فقط','Team members'=>'أعضاء الفريق','Create project'=>'إنشاء مشروع','Role'=>'الدور','Job title'=>'المسمى الوظيفي','Leave empty when editing to keep the current password.'=>'اتركه فارغًا عند التعديل للإبقاء على كلمة المرور الحالية.','Add member'=>'إضافة عضو','Department name'=>'اسم القسم','Code'=>'الرمز','Department manager'=>'مدير القسم','Not set'=>'غير محدد','Colour'=>'اللون','Save department'=>'حفظ القسم',
        'All projects'=>'كل المشاريع','Everyone'=>'الجميع','Any priority'=>'أي أولوية','Any status'=>'أي حالة','Overdue only'=>'المتأخرة فقط','Board view'=>'عرض اللوحة','Clear'=>'مسح','Drop tasks here'=>'أسقط المهام هنا','needs attention'=>'تحتاج إلى الانتباه','Nothing blocked 🎉'=>'لا توجد مهام متوقفة 🎉','Estimated'=>'مقدّر','comments'=>'تعليقات','files'=>'ملفات','Overview'=>'نظرة عامة','Tasks'=>'المهام','Team'=>'الفريق','Project team'=>'فريق المشروع','Project details'=>'تفاصيل المشروع','Quick facts'=>'معلومات سريعة','Danger zone'=>'منطقة الخطر','Delete project'=>'حذف المشروع','Save changes'=>'حفظ التغييرات','Workload by member'=>'عبء العمل حسب العضو','Priority tasks'=>'المهام ذات الأولوية','Recent activity'=>'النشاط الأخير','Nothing here.'=>'لا يوجد شيء هنا.','Nothing recorded yet.'=>'لم يتم تسجيل أي شيء بعد.','No critical or high-priority tasks open. 🎉'=>'لا توجد مهام حرجة أو عالية الأولوية مفتوحة. 🎉','Add a member…'=>'إضافة عضو…','Role in project'=>'الدور في المشروع','Remove from project'=>'إزالة من المشروع','All tasks'=>'كل المهام','Showing'=>'عرض','of'=>'من','Backlog'=>'قائمة الانتظار','To Do'=>'للتنفيذ','In Progress'=>'قيد التنفيذ','In Review'=>'قيد المراجعة','Done'=>'مكتملة','Blocked'=>'متوقفة','Low'=>'منخفضة','Medium'=>'متوسطة','High'=>'عالية','Critical'=>'حرجة','Planning'=>'تخطيط','On Hold'=>'معلقة','Completed'=>'مكتملة','Cancelled'=>'ملغاة','Owner'=>'المالك','Manager'=>'المدير','Viewer'=>'مشاهد',
        'All actions'=>'كل الإجراءات','Any object'=>'أي عنصر','From'=>'من','To'=>'إلى','Everyone'=>'الجميع','Export CSV'=>'تصدير CSV','Performance insights for the last'=>'رؤى الأداء لآخر','days'=>'أيام','Created'=>'أُنشئت','Completed'=>'مكتملة','Hours'=>'الساعات','Logged'=>'مسجلة','Estimated'=>'مقدرة','Conversion'=>'التحويل','Mobile UX'=>'تجربة المستخدم على الجوال','Retention'=>'الاحتفاظ','Optimization'=>'التحسين','Unknown'=>'غير معروف','Low task completion rate'=>'معدل إكمال المهام منخفض','High mobile usage with low completion rate'=>'استخدام مرتفع للجوال مع معدل إكمال منخفض','Continuous improvement opportunities'=>'فرص للتحسين المستمر',
        'Throughput — last 14 days'=>'الإنتاجية — آخر 14 يومًا','Tasks created vs. tasks completed'=>'المهام المنشأة مقابل المهام المكتملة','By status'=>'حسب الحالة','My tasks'=>'مهامي','Sorted by due date and priority'=>'مرتبة حسب تاريخ الاستحقاق والأولوية','View all'=>'عرض الكل','Mark as done'=>'تحديد كمكتملة','Team workload'=>'عبء عمل الفريق','Open tasks per member'=>'المهام المفتوحة لكل عضو','Open tasks — your project teammates'=>'المهام المفتوحة — زملاؤك في المشاريع','My week'=>'أسبوعي','Last 7 days'=>'آخر 7 أيام','hours logged'=>'ساعات مسجلة','tasks completed'=>'مهام مكتملة','unread notifications'=>'إشعارات غير مقروءة','My projects'=>'مشاريعي','Progress and deadlines'=>'التقدم والمواعيد النهائية','Needs an owner'=>'تحتاج إلى مسؤول','Unassigned open tasks'=>'مهام مفتوحة غير مسندة','See all'=>'عرض الكل','Full log'=>'السجل الكامل','No activity recorded yet.'=>'لم يتم تسجيل أي نشاط بعد.','Open'=>'مفتوحة','late'=>'متأخرة','Completion rate'=>'معدل الإكمال','Open tasks'=>'المهام المفتوحة','Active projects'=>'المشاريع النشطة','My open tasks'=>'مهامي المفتوحة','Blocked'=>'متوقفة',
        'Active Users (Weekly)'=>'المستخدمون النشطون (أسبوعيًا)','Task Success Rate'=>'معدل نجاح المهام','Average Completion Time'=>'متوسط وقت الإكمال','Mobile Usage'=>'استخدام الجوال','Last 30 Days'=>'آخر 30 يومًا','30 Days'=>'30 يومًا','7 Days'=>'7 أيام','Page'=>'الصفحة','Exit Count'=>'عدد مرات الخروج','Percentage'=>'النسبة','Recommendation'=>'التوصية','Urgent review required'=>'تحتاج إلى مراجعة عاجلة','Improvement suggested'=>'يُقترح تحسين','Good performance'=>'أداء جيد','Average Response Time'=>'متوسط زمن الاستجابة','Peak Hour'=>'ساعة الذروة','Slowest 5 Pages'=>'أبطأ 5 صفحات','Tip'=>'نصيحة','No active tests currently'=>'لا توجد اختبارات نشطة حاليًا','Start by creating a new A/B test to compare two different versions of a feature or page'=>'ابدأ بإنشاء اختبار A/B جديد لمقارنة نسختين مختلفتين من ميزة أو صفحة',
        'Roles'=>'الأدوار','User'=>'المستخدم','Password'=>'كلمة المرور','Login'=>'تسجيل الدخول',
    ];
    return $ar;
}

function t(string $text): string {
    $ar = locale_translations();
    return locale() === 'ar' ? ($ar[$text] ?? $text) : $text;
}
