"""Exact recurring meeting comparisons, including actual date intersections."""
import datetime as dt

DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']


def minutes(value):
    value = str(value).replace(':', '')
    return int(value[:2]) * 60 + int(value[2:4])


def meetings(source):
    result = []
    for m in source['meetings']:
        if not m.get('beginTime') or not m.get('endTime'):
            continue
        start, end = minutes(m['beginTime']), minutes(m['endTime'])
        if end <= start:
            raise ValueError('Invalid official meeting interval')
        lo = dt.datetime.strptime(m['startDate'], '%m/%d/%Y').date().toordinal()
        hi = dt.datetime.strptime(m['endDate'], '%m/%d/%Y').date().toordinal()
        for day, key in enumerate(DAYS):
            if m.get(key):
                result.append((day, start, end, lo, hi))
    return result


def overlap(a, b):
    for day, start, end, lo, hi in a:
        for otherday, otherstart, otherend, otherlo, otherhi in b:
            left, right = max(lo, otherlo), min(hi, otherhi)
            if day == otherday and start < otherend and otherstart < end and left <= right:
                if left + (day - dt.date.fromordinal(left).weekday()) % 7 <= right:
                    return True
    return False


def old_meetings(tables, section):
    """Reserve legacy graduate slots conservatively; never rewrite their times."""
    periods = {p['period_ID']: p for p in tables['Period']}
    days = {d['day_ID']: DAYS.index(d['day_Of_Week'].lower()) for d in tables['Day']}
    slot = section['time_Slot_ID']
    result = []
    for d in tables['Time_Slot_Day']:
        if d['time_Slot_ID'] != slot:
            continue
        for p in tables['Time_Slot_Period']:
            if p['time_Slot_ID'] == slot:
                period = periods[p['period_ID']]
                result.append((days[d['day_ID']], minutes(period['start_Time']), minutes(period['end_Time']), 1, 3652059))
    return result
