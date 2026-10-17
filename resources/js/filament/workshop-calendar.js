// Workshop calendar for the admin panel (WorkshopCalendar page). Loaded on demand through Filament's x-load.
import { Calendar } from '@fullcalendar/core';
import trLocale from '@fullcalendar/core/locales/tr';
import dayGridPlugin from '@fullcalendar/daygrid';
import interactionPlugin from '@fullcalendar/interaction';
import timeGridPlugin from '@fullcalendar/timegrid';

export default function workshopCalendar({ shopNow }) {
    return {
        calendar: null,

        init() {
            const wire = this.$wire;
            // "Today" and the now line follow the shop's clock (Europe/Istanbul), not the browser's.
            const clockOffset = Date.parse(shopNow + 'Z') - Date.now();

            this.calendar = new Calendar(this.$refs.calendar, {
                plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
                locale: trLocale,
                // Times are the shop's wall-clock times; "UTC" stops the browser's timezone from shifting them.
                timeZone: 'UTC',
                now: () => new Date(Date.now() + clockOffset),
                initialView: window.matchMedia('(max-width: 640px)').matches ? 'timeGridDay' : 'dayGridMonth',
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,timeGridDay' },
                height: 'auto',
                slotMinTime: '08:00:00',
                slotMaxTime: '23:00:00',
                scrollTime: '10:00:00',
                allDaySlot: false,
                nowIndicator: true,
                dayMaxEvents: 4,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
                displayEventEnd: true,
                selectable: true,
                selectMirror: true,
                editable: true,
                eventDurationEditable: true,

                events: (info, success, failure) => wire.fetchEvents(info.startStr, info.endStr).then(success, failure),

                select: (info) => {
                    this.calendar.unselect();
                    wire.mountAction('createSession', { start: info.startStr, end: info.endStr, allDay: info.allDay });
                },

                eventClick: (info) => wire.mountAction('session', { session: Number(info.event.id) }),

                eventDrop: (info) => this.move(info),
                eventResize: (info) => this.move(info),
            });

            this.calendar.render();
        },

        async move(info) {
            const end = info.event.endStr || info.event.startStr;
            const saved = await this.$wire.moveSession(Number(info.event.id), info.event.startStr, end);

            if (!saved) {
                info.revert();
            }
        },

        refresh() {
            this.calendar?.refetchEvents();
        },

        destroy() {
            this.calendar?.destroy();
        },
    };
}
