/* TaskFlow live locale switcher + dynamic Arabic translation support. */
(function () {
  'use strict';

  const extras = {
    'Activity log':'سجل النشاط','All tasks':'كل المهام','All statuses':'كل الحالات','All departments':'كل الأقسام','All roles':'كل الأدوار',
    'All actions':'كل الإجراءات','Any object':'أي عنصر','Any status':'أي حالة','Any priority':'أي أولوية','Board view':'عرض اللوحة',
    'List view':'عرض القائمة','New project':'مشروع جديد','New department':'قسم جديد','Create department':'إنشاء قسم','Create account':'إنشاء حساب',
    'Already registered?':'لديك حساب بالفعل؟','Confirm password':'تأكيد كلمة المرور','Work email':'بريد العمل','Join':'انضمام','and start collaborating.':'وابدأ التعاون.',
    'Password':'كلمة المرور','Email':'البريد الإلكتروني','Email address':'البريد الإلكتروني','Full name':'الاسم الكامل','Job title':'المسمى الوظيفي',
    'Department manager':'مدير القسم','Department name':'اسم القسم','No manager assigned':'لم يتم تعيين مدير','View members':'عرض الأعضاء','Completion rate':'معدل الإكمال',
    'Logged':'المسجل','Open tasks':'المهام المفتوحة','Current tasks':'المهام الحالية','Recently completed':'المكتملة مؤخرًا','Colleagues':'الزملاء',
    'Reports to':'يرفع تقاريره إلى','Joined':'انضم في','Last seen':'آخر ظهور','Hours logged':'الساعات المسجلة','Completed tasks per week':'المهام المكتملة أسبوعيًا',
    'Memberships and role in each':'العضويات والدور في كل مشروع','Not a member of any project yet.':'ليس عضوًا في أي مشروع حتى الآن.','No open tasks — all clear.':'لا توجد مهام مفتوحة — كل شيء على ما يرام.',
    'Open work assigned to':'العمل المفتوح المسند إلى','All':'الكل','Apply':'تطبيق','Reset':'إعادة ضبط','Export':'تصدير','Export CSV':'تصدير CSV','Clear':'مسح',
    'Unassigned':'غير مسندة','Private':'خاص','Public':'عام','Deadline':'الموعد النهائي','No deadline':'بدون موعد نهائي','Owner':'المالك','Project':'المشروع',
    'Team':'الفريق','Members':'الأعضاء','Tasks':'المهام','Status':'الحالة','Priority':'الأولوية','Progress':'التقدم','Distribution':'التوزيع',
    'Status breakdown':'تفصيل الحالات','Workload by member':'عبء العمل حسب العضو','Overall progress':'التقدم الإجمالي','Project details':'تفاصيل المشروع','Project team':'فريق المشروع',
    'Quick facts':'معلومات سريعة','Danger zone':'منطقة الخطر','Delete project':'حذف المشروع','Save changes':'حفظ التغييرات','Recent activity':'النشاط الأخير',
    'Nothing here.':'لا يوجد شيء هنا.','Nothing recorded yet.':'لم يتم تسجيل أي شيء بعد.','Nothing logged yet.':'لم يتم تسجيل أي سجلات بعد.','No projects to report on.':'لا توجد مشاريع لعرض تقارير عنها.',
    'No team data yet.':'لا توجد بيانات للفريق بعد.','No time has been logged in this period. Team members can log hours from any task page.':'لم يتم تسجيل أي وقت في هذه الفترة. يمكن لأعضاء الفريق تسجيل الساعات من أي صفحة مهمة.',
    'No hour estimates yet.':'لا توجد تقديرات للساعات بعد.','Per project, all time':'حسب المشروع، كل الوقت','Per task':'حسب المهمة','Time log detail':'تفاصيل سجل الوقت',
    'Estimate accuracy':'دقة التقدير','Estimate vs. actual':'التقديري مقابل الفعلي','Hours by member':'الساعات حسب العضو','Logged in the last':'المسجلة خلال آخر',
    'Most tasks completed in the last':'أكثر المهام إكمالًا خلال آخر','Top performers':'الأعلى أداءً','Throughput':'الإنتاجية','Status mix':'توزيع الحالات','By priority':'حسب الأولوية',
    'Comments':'التعليقات','Entries':'الإدخالات','Total':'الإجمالي','Done':'مكتملة','Open':'مفتوحة','Overdue':'متأخرة','Created':'أُنشئت','Completed':'مكتملة',
    'Today is':'اليوم هو','Last 7 days':'آخر 7 أيام','Last 8 weeks':'آخر 8 أسابيع','Last 30 Days':'آخر 30 يومًا','30 Days':'30 يومًا','7 Days':'7 أيام',
    'You have':'لديك','unread notification':'إشعار غير مقروء','unread notifications':'إشعارات غير مقروءة','tasks completed':'مهام مكتملة','open task':'مهمة مفتوحة','overdue':'متأخرة','late':'متأخرة','hours logged':'ساعات مسجلة',
    'My projects':'مشاريعي','My tasks':'مهامي','My week':'أسبوعي','Open board':'فتح اللوحة','See all':'عرض الكل','View all':'عرض الكل','Full log':'السجل الكامل',
    'Needs an owner':'تحتاج إلى مسؤول','Unassigned open tasks':'مهام مفتوحة غير مسندة','Progress and deadlines':'التقدم والمواعيد النهائية','Team workload':'عبء عمل الفريق',
    'Tasks created vs. tasks completed':'المهام المنشأة مقابل المهام المكتملة','Tasks created vs. tasks completed over the last':'المهام المنشأة مقابل المكتملة خلال آخر','Throughput — last 14 days':'الإنتاجية — آخر 14 يومًا',
    'Add sub-task':'إضافة مهمة فرعية','Add time':'إضافة وقت','Assignee':'المسند إليه','Back to the board':'العودة إلى اللوحة','Comment':'تعليق','Comments':'التعليقات','Ctrl + Enter to post':'اضغط Ctrl + Enter للنشر',
    'Delete this task':'حذف هذه المهمة','Details':'التفاصيل','Drop a file or':'أسقط ملفًا أو','Estimate':'التقدير','Files':'الملفات','Following (':'يتابع (','History':'السجل','Log work':'تسجيل العمل',
    'No comments yet — start the discussion.':'لا توجد تعليقات بعد — ابدأ النقاش.','No description yet.':'لا يوجد وصف بعد.','No files attached.':'لا توجد ملفات مرفقة.','No history recorded.':'لم يتم تسجيل أي سجل.',
    'No sub-tasks. Break this work into smaller steps to track progress precisely.':'لا توجد مهام فرعية. قسّم هذا العمل إلى خطوات أصغر لتتبع التقدم بدقة.',
    'Nobody yet':'لا أحد بعد','People':'الأشخاص','Project team (':'فريق المشروع (','Reporter':'المبلّغ','Sub-task of':'مهمة فرعية من','Sub-tasks':'المهام الفرعية','Due in':'متبقي على الاستحقاق',
    'days overdue':'أيام متأخرة','day':'يوم','days':'أيام','browse':'تصفح','completed':'مكتملة','Delete':'حذف','Edit':'تعديل','Board':'اللوحة',
    'Add member…':'إضافة عضو…','Role in project':'الدور في المشروع','Remove from project':'إزالة من المشروع','Select all':'تحديد الكل','Toggle module':'تبديل الوحدة',
    'Module / permission':'الوحدة / الصلاحية','Role name':'اسم الدور','Members with this role':'الأعضاء بهذا الدور','New roles start with the same permissions as':'تبدأ الأدوار الجديدة بنفس صلاحيات',
    'You can view permissions but not change them.':'يمكنك عرض الصلاحيات ولكن لا يمكنك تغييرها.','Built-in':'مدمج','Granted':'ممنوحة','selected':'محدد','permissions granted':'صلاحيات ممنوحة',
    'Delete role':'حذف الدور','Edit role':'تعديل الدور','Create role':'إنشاء دور','Save permissions':'حفظ الصلاحيات','Slug':'المعرّف','Lowercase letters, numbers and underscores. Leave empty to generate it from the name.':'أحرف صغيرة وأرقام وشرطات سفلية. اتركه فارغًا لإنشائه تلقائيًا من الاسم.',
    'Mark read':'تحديد كمقروء','Mark all read':'تحديد الكل كمقروء','Clear read':'مسح المقروء','Unread':'غير مقروءة','Notifications':'الإشعارات',
    'UX Audit & Analytics':'تدقيق تجربة المستخدم والتحليلات','A comprehensive dashboard to measure and improve user experience, conversion rates, and system performance':'لوحة شاملة لقياس وتحسين تجربة المستخدم ومعدلات التحويل وأداء النظام',
    'Active A/B Tests':'اختبارات A/B النشطة','Active Users (Weekly)':'المستخدمون النشطون (أسبوعيًا)','Average Completion Time':'متوسط وقت الإكمال','Average Response Time':'متوسط زمن الاستجابة','Mobile Usage':'استخدام الجوال',
    'Conversion Funnel - Registration':'مسار التحويل - التسجيل','Create First Test':'إنشاء أول اختبار','Data-driven UX Recommendations':'توصيات تجربة المستخدم المبنية على البيانات','Exit Count':'عدد مرات الخروج','Feature Adoption':'اعتماد الميزات','Keep response time under 200ms for an optimal user experience':'حافظ على زمن الاستجابة أقل من 200 مللي ثانية لتجربة مستخدم مثالية',
    'New Test':'اختبار جديد','No active tests currently':'لا توجد اختبارات نشطة حاليًا','Peak Hour':'ساعة الذروة','Percentage':'النسبة','Recommendation':'التوصية','Refresh':'تحديث','Slowest 5 Pages':'أبطأ 5 صفحات','System Performance':'أداء النظام','Task Success Rate':'معدل نجاح المهام','Top Exit Points':'أكثر نقاط الخروج',
    'Urgent review required':'تحتاج إلى مراجعة عاجلة','Good performance':'أداء جيد','Improvement suggested':'يُقترح تحسين','Tip':'نصيحة','Expected Impact:':'التأثير المتوقع:','UX & Performance Analysis':'تحليل تجربة المستخدم والأداء',
    'Restore default permissions':'استعادة الصلاحيات الافتراضية','Restore defaults':'استعادة الافتراضيات','Restore default permissions for':'استعادة الصلاحيات الافتراضية لـ','This will replace the current permissions for this role with the built-in defaults. All users with this role will use the restored permissions.':'سيؤدي ذلك إلى استبدال الصلاحيات الحالية لهذا الدور بالصلاحيات الافتراضية. سيستخدم جميع المستخدمين بهذا الدور الصلاحيات المستعادة.','Search':'بحث','Searching…':'جارٍ البحث…','No results for':'لا توجد نتائج لـ','Tasks':'المهام','Projects':'المشاريع','People':'الأشخاص','Loading…':'جارٍ التحميل…','Close':'إغلاق','Save':'حفظ','Cancel':'إلغاء','Create':'إنشاء','Update':'تحديث',
    'Delete permanently?':'حذف نهائيًا؟','Yes, delete':'نعم، احذف','This cannot be undone.':'لا يمكن التراجع عن هذا الإجراء.','Saved.':'تم الحفظ.','Save failed.':'فشل الحفظ.','Request failed.':'فشل الطلب.',
    'Nothing to update.':'لا يوجد شيء لتحديثه.','Your profile was saved.':'تم حفظ ملفك الشخصي.','Company settings saved.':'تم حفظ إعدادات الشركة.','System settings saved.':'تم حفظ إعدادات النظام.',
    'Current password':'كلمة المرور الحالية','New password':'كلمة المرور الجديدة','Confirm new password':'تأكيد كلمة المرور الجديدة','At least':'على الأقل','characters.':'أحرف.','Changing your password signs you out of every other device.':'سيؤدي تغيير كلمة المرور إلى تسجيل خروجك من جميع الأجهزة الأخرى.','Update password':'تحديث كلمة المرور',
    'Account':'الحساب','Permissions':'الصلاحيات','Data scope':'نطاق البيانات','Department':'القسم','Member since':'عضو منذ','Last sign-in':'آخر تسجيل دخول','Session IP':'عنوان IP للجلسة','My activity':'نشاطي',
    'Company profile':'ملف الشركة','Shown in the sidebar, on the login screen and in e-mails.':'يظهر في الشريط الجانبي وشاشة تسجيل الدخول ورسائل البريد الإلكتروني.','Company name':'اسم الشركة','Contact e-mail':'البريد الإلكتروني للتواصل','Address':'العنوان','Logo':'الشعار',
    'System preferences':'تفضيلات النظام','Default theme':'السمة الافتراضية','Light':'فاتح','Dark':'داكن','Items per page':'العناصر في الصفحة','Database contents':'محتويات قاعدة البيانات','Uploaded files on disk':'الملفات المرفوعة على القرص','Database driver':'محرك قاعدة البيانات',
    'Maintenance':'الصيانة','Sensitive':'حساس','Choose an image':'اختر صورة','Upload':'رفع','Save company settings':'حفظ إعدادات الشركة','Save system settings':'حفظ إعدادات النظام',
    'Enable e-mail notifications (uses PHP':'تفعيل إشعارات البريد الإلكتروني (تستخدم PHP','Allow people to create their own account from the login page':'السماح للأشخاص بإنشاء حساباتهم من صفحة تسجيل الدخول','Keep this off for internal company use.':'اترك هذا الخيار معطلًا للاستخدام الداخلي للشركة.',
    'Access denied':'تم رفض الوصول','Page not found':'الصفحة غير موجودة','The page you are looking for was moved, deleted, or never existed.':'الصفحة التي تبحث عنها نُقلت أو حُذفت أو لم تكن موجودة أصلًا.','Back to dashboard':'العودة إلى لوحة التحكم','Task board':'لوحة المهام',
    'Every task. Every teammate.':'كل مهمة. كل زميل.','One clear picture.':'رؤية واضحة واحدة.','Fine-grained roles & permissions':'أدوار وصلاحيات دقيقة','Kanban boards with drag & drop':'لوحات كانبان بالسحب والإفلات','Mentions, attachments and full audit trail':'الإشارات والمرفقات وسجل التدقيق الكامل','Workload, velocity and productivity reports':'تقارير عبء العمل والسرعة والإنتاجية',
    'Sign in':'تسجيل الدخول','Welcome back':'مرحبًا بعودتك','Sign in to your workspace to continue.':'سجّل الدخول إلى مساحة عملك للمتابعة.','Keep me signed in':'إبقائي مسجّلًا للدخول','Need an account?':'ليس لديك حساب؟','Create one':'إنشاء حساب','Demo accounts':'حسابات تجريبية','Fill admin credentials':'تعبئة بيانات دخول المدير',
    'Create your account':'أنشئ حسابك','Already registered?':'لديك حساب بالفعل؟','Confirm password':'تأكيد كلمة المرور','Work email':'بريد العمل','No accounts yet — import':'لا توجد حسابات بعد — استورد',
    'Skip to content':'تخطي إلى المحتوى','Toggle navigation':'تبديل التنقل','Toggle dark mode':'تبديل الوضع الداكن','Account menu':'قائمة الحساب','Search tasks, projects, people…':'ابحث في المهام والمشاريع والأشخاص…',
    'Manager':'المدير','Member':'عضو','Viewer':'مشاهد','Supervisor':'المشرف','Team Lead':'قائد الفريق','Super Admin':'مدير النظام','Active':'نشط','Inactive':'غير نشط','Suspended':'موقوف',
    'Back to projects':'العودة إلى المشاريع','Delete project':'حذف المشروع','Public — everyone can see it':'عام — يمكن للجميع رؤيته','Private — members only':'خاص — للأعضاء فقط','None':'لا يوجد','Not set':'غير محدد',
    'Task title':'عنوان المهمة','Select a project…':'اختر مشروعًا…','Estimate (hours)':'التقدير (بالساعات)','Parent task (optional)':'المهمة الرئيسية (اختياري)','None — this is a top-level task':'لا يوجد — هذه مهمة رئيسية',
    'Project name':'اسم المشروع','Short code':'الرمز المختصر','Used as the task key prefix (e.g. PORTAL-12).':'يُستخدم كبادئة لمفتاح المهمة (مثل PORTAL-12).','Public — everyone can see it':'عام — يمكن للجميع رؤيته','Team members':'أعضاء الفريق',
    'Department':'القسم','Colour':'اللون','Save department':'حفظ القسم','Code':'الرمز','None — this is a top-level task':'لا يوجد — هذه مهمة رئيسية',
    'Drop tasks here':'أسقط المهام هنا','needs attention':'تحتاج إلى الانتباه','Nothing blocked 🎉':'لا توجد مهام متوقفة 🎉','All projects':'كل المشاريع','Everyone':'الجميع','Overdue only':'المتأخرة فقط',
    'No activity recorded yet.':'لم يتم تسجيل أي نشاط بعد.','No team members found':'لم يتم العثور على أعضاء','Adjust the filters, or add a new member to your company.':'عدّل عوامل التصفية أو أضف عضوًا جديدًا إلى شركتك.',
    'Save profile':'حفظ الملف الشخصي','Personal information':'المعلومات الشخصية','Your avatar is shown on tasks, comments and notifications.':'تظهر صورتك الرمزية في المهام والتعليقات والإشعارات.','Change picture':'تغيير الصورة',
    'Send me an e-mail when I am assigned a task or mentioned in a comment':'أرسل لي بريدًا إلكترونيًا عند إسناد مهمة إليّ أو ذكري في تعليق','Avatar colour':'لون الصورة الرمزية','Used for your initials avatar.':'يُستخدم في الصورة الرمزية التي تعرض الأحرف الأولى.',
    'Roles & Permissions':'الأدوار والصلاحيات','Roles':'الأدوار','Administration':'الإدارة','Workspace':'مساحة العمل','Insights':'التقارير والتحليلات','Data & backup':'البيانات والنسخ الاحتياطي','Company':'الشركة','System':'النظام',
    'Member permissions':'صلاحيات العضو','New roles start with the same permissions as':'تبدأ الأدوار الجديدة بنفس صلاحيات','Built-in roles cannot be deleted.':'لا يمكن حذف الأدوار المدمجة.',
    'Create project':'إنشاء مشروع','Create task':'إنشاء مهمة','Create department':'إنشاء قسم','Save permissions':'حفظ الصلاحيات','Add member':'إضافة عضو','Unassigned':'غير مسندة'
  };


  // Additional UI vocabulary used by reports, UX audit, filters and system messages.
  Object.assign(extras, {
    'Add':'إضافة','Edit':'تعديل','Delete':'حذف','Following':'يتابع','Follow':'متابعة','Board':'اللوحة','List':'قائمة',
    'Board view':'عرض اللوحة','Open board':'فتح اللوحة','Open':'مفتوحة','Closed':'مغلقة','Completed':'مكتملة','Created':'تم الإنشاء',
    'In progress':'قيد التنفيذ','Blocked':'متوقفة','Critical':'حرجة','High':'عالية','Medium':'متوسطة','Low':'منخفضة',
    'Any object':'أي عنصر','All actions':'كل الإجراءات','All statuses':'كل الحالات','Colleagues':'الزملاء','History':'السجل',
    'Details':'التفاصيل','Deadline':'الموعد النهائي','Reporter':'المُبلّغ','Reports to':'يرفع التقارير إلى','Current tasks':'المهام الحالية',
    'My projects':'مشاريعي','My week':'أسبوعي','Needs an owner':'تحتاج إلى مالك','Unassigned open tasks':'المهام المفتوحة غير المسندة',
    'Recently completed':'المكتملة مؤخرًا','Total':'الإجمالي','Average':'المتوسط','Percentage':'النسبة المئوية','Completion':'الإكمال',
    'Completion rate':'معدل الإكمال','Average Completion Time':'متوسط وقت الإكمال','Average Response Time':'متوسط وقت الاستجابة',
    'Tasks created vs. completed over the last':'المهام المنشأة مقابل المكتملة خلال آخر','Tasks created vs. tasks completed':'المهام المنشأة مقابل المهام المكتملة',
    'By priority':'حسب الأولوية','By status':'حسب الحالة','Status mix':'توزيع الحالات','Throughput':'معدل الإنجاز','Peak Hour':'ساعة الذروة',
    'System Performance':'أداء النظام','System Performance':'أداء النظام','Slowest 5 Pages':'أبطأ 5 صفحات','Keep response time under 200ms for an optimal user experience':'حافظ على زمن استجابة أقل من 200 مللي ثانية لتجربة مستخدم مثالية',
    'UX Audit & Analytics':'تدقيق تجربة المستخدم والتحليلات','UX Audit':'تدقيق تجربة المستخدم','Data-driven UX Recommendations':'توصيات تجربة المستخدم المبنية على البيانات',
    'Data-driven UX Recommendations':'توصيات تجربة المستخدم المبنية على البيانات','Recommendation':'توصية','Expected Impact:':'الأثر المتوقع:','Improvement suggested':'تحسين مقترح',
    'Active A/B Tests':'اختبارات A/B النشطة','No active tests currently':'لا توجد اختبارات نشطة حاليًا','Create First Test':'إنشاء أول اختبار',
    'Start by creating a new A/B test to compare two different versions of a feature or page':'ابدأ بإنشاء اختبار A/B جديد لمقارنة نسختين مختلفتين من ميزة أو صفحة',
    'Feature Adoption':'اعتماد الميزات','Mobile Usage':'الاستخدام عبر الهاتف','Top Exit Points':'أكثر نقاط الخروج','Exit Count':'عدد مرات الخروج',
    'Conversion Funnel - Registration':'مسار التحويل - التسجيل','Conversion Drop-off:':'انخفاض التحويل:','Active Users (Weekly)':'المستخدمون النشطون (أسبوعيًا)',
    'Task Success Rate':'معدل نجاح المهام','Completed tasks per week':'المهام المكتملة أسبوعيًا','Progress and deadlines':'التقدم والمواعيد النهائية',
    'Team workload':'عبء عمل الفريق','Team performance':'أداء الفريق','Top performers':'الأعلى أداءً','Most tasks completed in the last':'أكثر المهام إكمالًا خلال آخر',
    '7 Days':'7 أيام','30 Days':'30 يومًا','Last 7 days':'آخر 7 أيام','Last 30 Days':'آخر 30 يومًا','Last 8 weeks':'آخر 8 أسابيع',
    'Last 30 days':'آخر 30 يومًا','Logged in the last':'المسجلة خلال آخر','Per project, all time':'حسب المشروع، منذ البداية','Per task':'حسب المهمة',
    'Time log detail':'تفاصيل سجل الوقت','Hours (logged / est.)':'الساعات (المسجلة / المقدرة)','Estimate':'التقدير','Estimated':'مقدّرة','Logged':'مسجلة',
    'No deadline':'لا يوجد موعد نهائي','No description yet.':'لا يوجد وصف بعد.','No files attached.':'لا توجد ملفات مرفقة.','No history recorded.':'لا يوجد سجل بعد.',
    'No comments yet — start the discussion.':'لا توجد تعليقات بعد — ابدأ النقاش.','No hour estimates yet.':'لا توجد تقديرات للساعات بعد.',
    'No open tasks — all clear.':'لا توجد مهام مفتوحة — كل شيء على ما يرام.','No team data yet.':'لا توجد بيانات للفريق بعد.','Nobody yet':'لا يوجد أحد بعد',
    'Nothing logged yet.':'لم يتم تسجيل شيء بعد.','Nothing here.':'لا يوجد شيء هنا.','Nothing recorded yet.':'لم يتم تسجيل أي شيء بعد.',
    'No projects to report on.':'لا توجد مشاريع لعرض تقارير عنها.','No sub-tasks. Break this work into smaller steps to track progress precisely.':'لا توجد مهام فرعية. قسّم هذا العمل إلى خطوات أصغر لتتبع التقدم بدقة.',
    'No team members found':'لم يتم العثور على أعضاء الفريق','No manager assigned':'لم يتم تعيين مدير','View members':'عرض الأعضاء','Memberships and role in each':'العضويات والدور في كل مشروع',
    'New roles start with the same permissions as':'تبدأ الأدوار الجديدة بنفس صلاحيات','Lowercase letters, numbers and underscores. Leave empty to generate it from the name.':'أحرف صغيرة وأرقام وشرطات سفلية. اتركه فارغًا لإنشائه تلقائيًا من الاسم.',
    'What is this role responsible for?':'ما مسؤوليات هذا الدور؟','Project Coordinator':'منسق المشروع','Module / permission':'الوحدة / الصلاحية','Toggle module':'تبديل الوحدة',
    'Restore default permissions':'استعادة الصلاحيات الافتراضية','Restore defaults':'استعادة الافتراضيات','Restore default permissions for':'استعادة الصلاحيات الافتراضية لـ',
    'This will replace the current permissions for this role with the built-in defaults. All users with this role will use the restored permissions.':'سيؤدي ذلك إلى استبدال الصلاحيات الحالية لهذا الدور بالصلاحيات الافتراضية المدمجة. سيستخدم جميع المستخدمين بهذا الدور الصلاحيات المستعادة.',
    'Select all':'تحديد الكل','Clear':'مسح','Clear read':'مسح المقروء','Mark read':'تحديد كمقروء','See all':'عرض الكل','View all':'عرض الكل',
    'Export CSV':'تصدير CSV','Export Report':'تصدير التقرير','Refresh':'تحديث','Open work assigned to':'فتح العمل المسند إلى','Their tasks':'مهامهم',
    'Your task list is clear — nice work!':'قائمة مهامك فارغة — عمل رائع!','You are all caught up':'لقد اطلعت على كل شيء','unread notification':'إشعار غير مقروء','permissions granted':'صلاحيات ممنوحة',
    'Members with this role':'الأعضاء بهذا الدور','You can view permissions but not change them.':'يمكنك عرض الصلاحيات ولكن لا يمكنك تغييرها.',
    'Built-in':'مدمج','Granted':'ممنوحة','selected':'محدد','Roles':'الأدوار','Role name':'اسم الدور','Slug':'المعرّف',
    'Page':'صفحة','Apply':'تطبيق','Reset':'إعادة ضبط','Any priority':'أي أولوية','Any status':'أي حالة','All departments':'كل الأقسام','All roles':'كل الأدوار',
    'Sort: Name':'ترتيب: الاسم','Sort: Open tasks':'ترتيب: المهام المفتوحة','Sort: Completed':'ترتيب: المكتملة','Sort: Hours logged':'ترتيب: الساعات المسجلة','Sort: Department':'ترتيب: القسم','Sort: Newest':'ترتيب: الأحدث',
    'Add a member…':'إضافة عضو…','Add sub-task':'إضافة مهمة فرعية','Add time':'إضافة وقت','Log work':'تسجيل العمل','Comment':'تعليق','Comments':'التعليقات','Files':'الملفات',
    'Drop a file or':'أسقط ملفًا أو','Choose an image':'اختر صورة','Square images look best. Max':'تظهر الصور المربعة بشكل أفضل. الحد الأقصى',
    'Company profile':'ملف الشركة','Shown in the sidebar, on the login screen and in e-mails.':'يظهر في الشريط الجانبي وشاشة تسجيل الدخول ورسائل البريد الإلكتروني.',
    'Maintenance':'الصيانة','Sensitive':'حساس','Database contents':'محتويات قاعدة البيانات','Uploaded files on disk':'الملفات المرفوعة على القرص',
    'Download your data as CSV. Exports respect the current permission scope.':'نزّل بياناتك بصيغة CSV. تحترم عمليات التصدير نطاق الصلاحيات الحالي.',
    'The installer':'المثبّت','setup.php':'setup.php','config/env.php':'config/env.php','database/setup.sql':'database/setup.sql','database/setup.sql':'database/setup.sql',
    'Debug mode is currently':'وضع التصحيح مفعّل حاليًا','before going live so error details are never shown to visitors.':'قبل تشغيل الموقع فعليًا حتى لا تظهر تفاصيل الأخطاء للزوار.',
    'Your server also limits uploads via':'يحد خادمك أيضًا من الرفع عبر','function is not available on this server. Configure SMTP in php.ini or install an SMTP extension.':'الدالة غير متاحة على هذا الخادم. اضبط SMTP في php.ini أو ثبّت إضافة SMTP.',
    'Old sessions are cleaned automatically on roughly one request in twenty, so there is nothing to run by hand here.':'تُنظّف الجلسات القديمة تلقائيًا تقريبًا في طلب واحد من كل عشرين، لذلك لا تحتاج إلى تشغيل أي إجراء يدوي هنا.',
    'Public':'عام','Private':'خاص','Visibility':'الظهور','Owner':'المالك','Active':'نشط','Inactive':'غير نشط','Suspended':'موقوف',
    'Role in project':'الدور في المشروع','Remove from project':'إزالة من المشروع','Delete project':'حذف المشروع','Delete role':'حذف الدور','Edit role':'تعديل الدور',
    'Create role':'إنشاء دور','Create project':'إنشاء مشروع','Create task':'إنشاء مهمة','Create department':'إنشاء قسم','Save permissions':'حفظ الصلاحيات',
    'Save company settings':'حفظ إعدادات الشركة','Save system settings':'حفظ إعدادات النظام','Save department':'حفظ القسم','Save profile':'حفظ الملف الشخصي','Update password':'تحديث كلمة المرور',
    'Current password':'كلمة المرور الحالية','New password':'كلمة المرور الجديدة','Confirm new password':'تأكيد كلمة المرور الجديدة','Changing your password signs you out of every other device.':'سيؤدي تغيير كلمة المرور إلى تسجيل خروجك من جميع الأجهزة الأخرى.',
    'Work email':'بريد العمل','Need an account?':'ليس لديك حساب؟','Already registered?':'لديك حساب بالفعل؟','Create account':'إنشاء حساب','Create your account':'أنشئ حسابك','Confirm password':'تأكيد كلمة المرور',
    'Incorrect email or password.':'البريد الإلكتروني أو كلمة المرور غير صحيحة.','Please enter your email and password.':'أدخل بريدك الإلكتروني وكلمة المرور.','Access denied':'تم رفض الوصول','Page not found':'الصفحة غير موجودة',
    'Back to dashboard':'العودة إلى لوحة التحكم','Back to projects':'العودة إلى المشاريع','Back to team':'العودة إلى الفريق','Back to the board':'العودة إلى اللوحة',
    'New task':'مهمة جديدة','New project':'مشروع جديد','New department':'قسم جديد','New role':'دور جديد','Add member':'إضافة عضو','New roles start with the same permissions as':'تبدأ الأدوار الجديدة بنفس صلاحيات'
  });

  const translations = Object.assign({}, window.TASKFLOW_TRANSLATIONS || {}, extras);
  let active = document.documentElement.lang === 'ar';
  let translating = false;
  const originalText = new WeakMap();
  const originalAttrs = new WeakMap();

  function tr(value) {
    const raw = String(value ?? '');
    const trimmed = raw.trim();
    if (!trimmed) return raw;
    if (translations[trimmed]) return raw.replace(trimmed, translations[trimmed]);

    // Common dynamic labels containing numbers.
    let m = trimmed.match(/^(\d+)\s+days?$/i);
    if (m) return raw.replace(trimmed, `${m[1]} ${m[1] === '1' ? 'يوم' : 'أيام'}`);
    m = trimmed.match(/^(\d+)\s+hours?$/i);
    if (m) return raw.replace(trimmed, `${m[1]} ${m[1] === '1' ? 'ساعة' : 'ساعات'}`);
    m = trimmed.match(/^([\d.]+)%\s+complete$/i);
    if (m) return raw.replace(trimmed, `${m[1]}% مكتمل`);
    return raw;
  }

  function translate(root = document.body) {
    if (!active || translating || !root) return;
    translating = true;
    try {
      const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
          const p = node.parentElement;
          if (!p || ['SCRIPT','STYLE','NOSCRIPT','TEXTAREA'].includes(p.tagName)) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      });
      const nodes = [];
      while (walker.nextNode()) nodes.push(walker.currentNode);
      nodes.forEach(n => {
        if (!originalText.has(n)) originalText.set(n, n.nodeValue);
        const source = originalText.get(n);
        const next = active ? tr(source) : source;
        if (next !== n.nodeValue) n.nodeValue = next;
      });
      root.querySelectorAll?.('[placeholder],[title],[aria-label],[data-i18n],[value]').forEach(el => {
        let saved = originalAttrs.get(el);
        if (!saved) { saved = {}; originalAttrs.set(el, saved); }
        ['placeholder','title','aria-label'].forEach(attr => {
          const current = el.getAttribute(attr);
          if (current === null) return;
          if (!(attr in saved)) saved[attr] = current;
          const source = saved[attr];
          const next = active ? tr(source) : source;
          if (next !== current) el.setAttribute(attr, next);
        });
      });
      if (root === document.body && document.title) {
        if (!originalAttrs.has(document.documentElement)) originalAttrs.set(document.documentElement, {});
        const savedTitle = originalAttrs.get(document.documentElement);
        if (!('data-title' in savedTitle)) savedTitle['data-title'] = document.title;
        document.title = active ? tr(savedTitle['data-title']) : savedTitle['data-title'];
      }
      root.querySelectorAll?.('option').forEach(o => {
        if (!originalText.has(o)) originalText.set(o, o.textContent);
        const source = originalText.get(o);
        const next = active ? tr(source) : source;
        if (next !== o.textContent) o.textContent = next;
      });
    } finally { translating = false; }
  }

  function setDocumentLocale(locale) {
    active = locale === 'ar';
    document.documentElement.lang = locale;
    document.documentElement.dir = active ? 'rtl' : 'ltr';
    document.body?.classList.toggle('locale-ar', active);
    document.documentElement.style.direction = active ? 'rtl' : 'ltr';
    translate();
  }

  async function changeLocale(select) {
    const locale = select.value === 'ar' ? 'ar' : 'en';
    const form = select.closest('form');
    if (!form) return;
    const fd = new FormData(form);
    fd.set('locale', locale);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf && !fd.has('_token')) fd.append('_token', csrf);
    select.disabled = true;
    try {
      const response = await fetch(form.action, { method: 'POST', body: fd, credentials: 'same-origin', headers: {'X-Requested-With':'XMLHttpRequest'} });
      const contentType = response.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) throw new Error('تعذر حفظ اللغة. أعد المحاولة.');
      const data = await response.json();
      if (!data.ok) throw new Error(data.error || 'Failed to change language.');
      setDocumentLocale(locale);
      // Translate the page immediately; no refresh is needed.
      translate(document.body);
      document.querySelectorAll('select[name="locale"]').forEach(s => { s.value = locale; });
      window.dispatchEvent(new CustomEvent('taskflow:locale-changed', { detail: { locale } }));
    } catch (e) {
      // Restore the previous selection if the request failed.
      select.value = active ? 'ar' : 'en';
      if (window.TaskFlow?.toast) window.TaskFlow.toast(e.message, 'error');
    } finally { select.disabled = false; }
  }

  document.addEventListener('change', e => {
    if (e.target.matches('select[name="locale"]')) changeLocale(e.target);
  }, true);

  window.TaskFlowI18n = { translate, setLocale: setDocumentLocale, translations };

  // Watch dynamic UI regardless of the initial locale. This matters when the
  // user switches from English to Arabic without a page refresh: modals, search
  // results, notifications and other AJAX-rendered nodes must be translated too.
  if (document.body) {
    const observer = new MutationObserver(mutations => {
      if (!active) return;
      mutations.forEach(m => m.addedNodes.forEach(n => {
        if (n.nodeType === 1) translate(n);
      }));
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }
  if (active) translate();
})();
